import Foundation

public struct GlobalSearchResponse: Decodable, Sendable {
    public let success: Bool?
    public let query: String
    public let sections: [String: GlobalSearchSection]
    public let total: Int
}

public struct GlobalSearchSection: Decodable, Sendable {
    public let definition: GlobalSearchSectionDefinition?
    public let items: [GlobalSearchItem]
}

public struct GlobalSearchSectionDefinition: Decodable, Sendable, Equatable {
    public let label: String?
    public let type: String?
}

public struct GlobalSearchItem: Decodable, Sendable, Identifiable, Equatable, Hashable {
    public let type: String
    public let id: Int
    public let organizationId: Int?
    public let title: String
    public let code: String?
    public let status: String?
    public let statusGroup: String?
    public let client: String?
    public let assignee: String?
    public let workedMinutes: Int?
    public let workedLabel: String?
    public let subtitle: String?
    public let role: String?
    public let url: String?
    public let updatedAt: String?

    public var stableID: String {
        "\(type)-\(id)"
    }
}
