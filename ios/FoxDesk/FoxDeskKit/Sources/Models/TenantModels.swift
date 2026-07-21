import Foundation

public struct TenantStatePayload: Decodable, Sendable, Equatable {
    public let tenant: TenantSummary
    public let access: TenantAccessState
}

public struct TenantSummary: Decodable, Sendable, Equatable, Identifiable, Hashable {
    public let id: Int
    public let name: String
    public let slug: String?
    public let status: String?
}

public struct TenantAccessState: Decodable, Sendable, Equatable, Hashable {
    public let allowed: Bool
    public let reason: String?
    public let state: String?
    public let message: String?
}
