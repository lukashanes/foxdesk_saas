import Foundation
import Observation

@MainActor
@Observable
public final class AppSession {
    public enum State: Equatable {
        case signedOut
        case signingIn
        case requiresTwoFactor(challengeToken: String)
        case signedIn
        case failed(message: String)
    }

    public private(set) var state: State = .signedOut
    public private(set) var user: FoxDeskUser?
    public private(set) var tokens: MobileSessionTokens?
    public private(set) var tenantState: TenantStatePayload?
    public private(set) var tenantStateError: String?
    public private(set) var isLoadingTenantState = false

    public let client: FoxDeskAPIClient
    public let tokenStore: TokenStore
    public let device: DeviceContext

    public init(
        client: FoxDeskAPIClient,
        tokenStore: TokenStore = KeychainTokenStore(),
        device: DeviceContext
    ) {
        self.client = client
        self.tokenStore = tokenStore
        self.device = device
    }

    public func restore() async {
        do {
            guard let storedTokens = try await tokenStore.loadTokens() else {
                state = .signedOut
                return
            }
            tokens = storedTokens
            let response = try await authenticated { accessToken in
                try await client.me(accessToken: accessToken)
            }
            user = response.user
            state = .signedIn
            await refreshTenantState()
        } catch {
            try? await tokenStore.clearTokens()
            tokens = nil
            user = nil
            tenantState = nil
            tenantStateError = nil
            state = .signedOut
        }
    }

    public func signIn(email: String, password: String) async {
        state = .signingIn
        do {
            let response = try await client.login(email: email, password: password, device: device)
            if response.requires2fa, let challenge = response.challengeToken {
                state = .requiresTwoFactor(challengeToken: challenge)
                return
            }
            try await finishAuthentication(response)
        } catch {
            state = .failed(message: error.localizedDescription)
        }
    }

    public func verifyTwoFactor(challengeToken: String, code: String) async {
        state = .signingIn
        do {
            let response = try await client.verifyTwoFactor(
                challengeToken: challengeToken,
                code: code,
                device: device
            )
            try await finishAuthentication(response)
        } catch {
            state = .failed(message: error.localizedDescription)
        }
    }

    public func signOut() async {
        if let tokens {
            _ = try? await client.unregisterDevice(accessToken: tokens.accessToken, device: device)
            _ = try? await client.logout(refreshToken: tokens.refreshToken, accessToken: tokens.accessToken)
        }
        try? await tokenStore.clearTokens()
        tokens = nil
        user = nil
        tenantState = nil
        tenantStateError = nil
        state = .signedOut
    }

    public var workspaceAccessAllowed: Bool {
        tenantState?.access.allowed ?? true
    }

    public func authenticated<Response>(
        _ operation: (String) async throws -> Response
    ) async throws -> Response {
        guard let accessToken = tokens?.accessToken else {
            throw FoxDeskAPIError.unauthorized
        }

        do {
            return try await operation(accessToken)
        } catch FoxDeskAPIError.unauthorized {
            let refreshedTokens = try await refreshStoredSession()
            return try await operation(refreshedTokens.accessToken)
        }
    }

    public func refreshTenantState() async {
        isLoadingTenantState = true
        tenantStateError = nil
        defer { isLoadingTenantState = false }

        do {
            tenantState = try await authenticated { accessToken in
                try await client.tenantState(accessToken: accessToken)
            }.data
        } catch {
            tenantStateError = error.localizedDescription
        }
    }

    private func finishAuthentication(_ response: MobileAuthResponse) async throws {
        guard let session = response.session, let user = response.user else {
            throw FoxDeskAPIError.invalidResponse
        }
        try await tokenStore.saveTokens(session)
        tokens = session
        self.user = user
        state = .signedIn
        await refreshTenantState()
    }

    private func refreshStoredSession() async throws -> MobileSessionTokens {
        guard let refreshToken = tokens?.refreshToken else {
            throw FoxDeskAPIError.unauthorized
        }

        let response = try await client.refresh(refreshToken: refreshToken, device: device)
        guard let session = response.session else {
            throw FoxDeskAPIError.invalidResponse
        }

        try await tokenStore.saveTokens(session)
        tokens = session
        if let refreshedUser = response.user {
            user = refreshedUser
        }
        state = .signedIn
        return session
    }
}
