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
    public let localDataStore: LocalSessionDataStore

    private var refreshTask: Task<MobileAuthResponse, Error>?
    private var sessionGeneration = 0

    public init(
        client: FoxDeskAPIClient,
        tokenStore: TokenStore = KeychainTokenStore(),
        device: DeviceContext,
        localDataStore: LocalSessionDataStore = LocalSessionDataStore()
    ) {
        self.client = client
        self.tokenStore = tokenStore
        self.device = device
        self.localDataStore = localDataStore
    }

    public func restore() async {
        let generation = sessionGeneration
        state = .signingIn
        var storedTokens: MobileSessionTokens?

        do {
            guard let loadedTokens = try await tokenStore.loadTokens() else {
                guard generation == sessionGeneration else { return }
                await localDataStore.clearAll()
                state = .signedOut
                return
            }
            storedTokens = loadedTokens
            guard generation == sessionGeneration else { return }
            tokens = loadedTokens
            let response = try await authenticated { accessToken in
                try await client.me(accessToken: accessToken)
            }
            guard generation == sessionGeneration else { return }
            user = response.user
            state = .signedIn
            await refreshTenantState()
        } catch {
            guard generation == sessionGeneration else { return }

            if isTerminalSessionError(error) {
                try? await tokenStore.clearTokens()
                await localDataStore.clearAll()
                tokens = nil
                user = nil
                tenantState = nil
                tenantStateError = nil
                state = .signedOut
            } else {
                // A timeout, temporary server error, cancellation, or decoding
                // problem must not destroy an otherwise valid refresh session.
                tokens = storedTokens
                state = .failed(message: error.localizedDescription)
            }
        }
    }

    public func signIn(email: String, password: String) async {
        sessionGeneration &+= 1
        refreshTask?.cancel()
        refreshTask = nil
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
        sessionGeneration &+= 1
        refreshTask?.cancel()
        refreshTask = nil
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
        sessionGeneration &+= 1
        refreshTask?.cancel()
        refreshTask = nil
        let signedOutUserID = user?.id

        if let tokens {
            _ = try? await client.unregisterDevice(accessToken: tokens.accessToken, device: device)
            _ = try? await client.logout(refreshToken: tokens.refreshToken, device: device)
        }
        try? await tokenStore.clearTokens()
        if let signedOutUserID {
            await localDataStore.clear(userId: signedOutUserID)
        }
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
            if let currentAccessToken = tokens?.accessToken, currentAccessToken != accessToken {
                return try await operation(currentAccessToken)
            }
            let refreshedTokens = try await refreshStoredSession()
            return try await operation(refreshedTokens.accessToken)
        } catch let error as FoxDeskAPIError {
            if case .server(let statusCode, _) = error, statusCode == 402 {
                await refreshTenantState()
            }
            throw error
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
        sessionGeneration &+= 1
        refreshTask?.cancel()
        refreshTask = nil
        try await tokenStore.saveTokens(session)
        tokens = session
        self.user = user
        state = .signedIn
        await refreshTenantState()
    }

    private func refreshStoredSession() async throws -> MobileSessionTokens {
        let generation = sessionGeneration
        if let refreshTask {
            let response = try await refreshTask.value
            return try await persistRefreshedSession(from: response, generation: generation)
        }

        guard let refreshToken = tokens?.refreshToken else {
            throw FoxDeskAPIError.unauthorized
        }

        let task = Task {
            try await client.refresh(refreshToken: refreshToken, device: device)
        }
        refreshTask = task

        do {
            let response = try await task.value
            refreshTask = nil
            return try await persistRefreshedSession(from: response, generation: generation)
        } catch {
            refreshTask = nil
            throw error
        }
    }

    private func persistRefreshedSession(
        from response: MobileAuthResponse,
        generation: Int
    ) async throws -> MobileSessionTokens {
        guard let session = response.session else {
            throw FoxDeskAPIError.invalidResponse
        }
        guard generation == sessionGeneration else {
            throw FoxDeskAPIError.unauthorized
        }

        try await tokenStore.saveTokens(session)
        guard generation == sessionGeneration else {
            try? await tokenStore.clearTokens()
            throw FoxDeskAPIError.unauthorized
        }
        tokens = session
        if let refreshedUser = response.user {
            user = refreshedUser
        }
        state = .signedIn
        return session
    }

    private func isTerminalSessionError(_ error: Error) -> Bool {
        if case FoxDeskAPIError.unauthorized = error {
            return true
        }
        return false
    }
}
