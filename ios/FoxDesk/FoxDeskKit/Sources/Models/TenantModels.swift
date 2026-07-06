import Foundation

public struct TenantStatePayload: Decodable, Sendable, Equatable {
    public let tenant: TenantSummary
    public let access: TenantAccessState
    public let billingActions: TenantBillingActions?
    public let usage: JSONValue?
    public let capabilities: TenantCapabilities?
    public let links: JSONValue?
}

public struct TenantSummary: Decodable, Sendable, Equatable, Identifiable, Hashable {
    public let id: Int
    public let name: String
    public let slug: String?
    public let status: String?
    public let subscriptionStatus: String?
    public let billingEmail: String?
    public let billingOverrideReason: String?
    public let trialEndsAt: String?
    public let suspendedAt: String?
}

public struct TenantAccessState: Decodable, Sendable, Equatable, Hashable {
    public let allowed: Bool
    public let reason: String?
    public let state: String?
    public let message: String?
}

public struct TenantBillingActions: Decodable, Sendable, Equatable, Hashable {
    public let showCheckout: Bool?
    public let checkoutLabel: String?
    public let showPortal: Bool?
    public let portalLabel: String?
    public let noticeTitle: String?
    public let noticeBody: String?
    public let noticeVariant: String?
}

public struct TenantCapabilities: Decodable, Sendable, Equatable, Hashable {
    public let manageBilling: Bool?
    public let platformAdmin: Bool?
}
