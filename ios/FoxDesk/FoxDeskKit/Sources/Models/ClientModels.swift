import Foundation

public struct ClientOverviewPayload: Decodable, Sendable, Equatable {
    public let client: ClientSummary
    public let view: String?
    public let counts: ClientTicketCounts?
    public let tickets: [TicketSummary]
    public let contacts: [ClientContact]
    public let time: ClientTimeSummary?
    public let links: JSONValue?
}

public struct ClientSummary: Decodable, Sendable, Equatable, Identifiable, Hashable {
    public let id: Int
    public let name: String
    public let email: String?
    public let phone: String?
    public let isActive: Bool?
    public let billableRate: Double?
}

public struct ClientTicketCounts: Decodable, Sendable, Equatable, Hashable {
    public let open: Int?
    public let waiting: Int?
    public let done: Int?
    public let archived: Int?
    public let all: Int?
}

public struct ClientContact: Decodable, Sendable, Equatable, Identifiable, Hashable {
    public let id: Int
    public let name: String?
    public let email: String?
    public let phone: String?
    public let role: String?
    public let isPrimary: Bool?
}

public struct ClientTimeSummary: Decodable, Sendable, Equatable, Hashable {
    public let minutes: Int?
    public let billableMinutes: Int?
    public let billableAmount: Double?
    public let minutesLabel: String?
    public let billableAmountLabel: String?
}
