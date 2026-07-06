import Foundation

public protocol TokenStore: Sendable {
    func loadTokens() async throws -> MobileSessionTokens?
    func saveTokens(_ tokens: MobileSessionTokens) async throws
    func clearTokens() async throws
}

public actor InMemoryTokenStore: TokenStore {
    private var tokens: MobileSessionTokens?

    public init(tokens: MobileSessionTokens? = nil) {
        self.tokens = tokens
    }

    public func loadTokens() async throws -> MobileSessionTokens? {
        tokens
    }

    public func saveTokens(_ tokens: MobileSessionTokens) async throws {
        self.tokens = tokens
    }

    public func clearTokens() async throws {
        tokens = nil
    }
}

