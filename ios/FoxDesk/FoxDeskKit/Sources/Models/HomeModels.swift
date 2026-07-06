import Foundation

public struct HomePayload: Decodable, Sendable {
    public let home: HomeFeed
}

public struct HomeFeed: Codable, Sendable, Equatable {
    public let schemaVersion: Int?
    public let generatedAt: String?
    public let limit: Int?
    public let work: [String: HomeQueueSection]?
    public let inbox: [String: HomeQueueSection]?
    public let timers: [HomeTimer]?
    public let time: HomeTimeActivity?
    public let notifications: HomeNotificationSummary?

    private enum CodingKeys: String, CodingKey {
        case schemaVersion
        case generatedAt
        case limit
        case work
        case inbox
        case timers
        case time
        case notifications
    }

    public init(
        schemaVersion: Int? = nil,
        generatedAt: String? = nil,
        limit: Int? = nil,
        work: [String: HomeQueueSection]? = nil,
        inbox: [String: HomeQueueSection]? = nil,
        timers: [HomeTimer]? = nil,
        time: HomeTimeActivity? = nil,
        notifications: HomeNotificationSummary? = nil
    ) {
        self.schemaVersion = schemaVersion
        self.generatedAt = generatedAt
        self.limit = limit
        self.work = work
        self.inbox = inbox
        self.timers = timers
        self.time = time
        self.notifications = notifications
    }

    public init(from decoder: Decoder) throws {
        let container = try decoder.container(keyedBy: CodingKeys.self)
        schemaVersion = try container.decodeIfPresent(Int.self, forKey: .schemaVersion)
        generatedAt = try container.decodeIfPresent(String.self, forKey: .generatedAt)
        limit = try container.decodeIfPresent(Int.self, forKey: .limit)
        work = try? container.decode([String: HomeQueueSection].self, forKey: .work)
        inbox = try? container.decode([String: HomeQueueSection].self, forKey: .inbox)
        timers = (try? container.decode([HomeTimer].self, forKey: .timers)) ?? []
        time = try? container.decode(HomeTimeActivity.self, forKey: .time)
        notifications = try container.decodeIfPresent(HomeNotificationSummary.self, forKey: .notifications)
    }
}

public struct HomeQueueSection: Codable, Sendable, Equatable {
    public let definition: HomeQueueDefinition?
    public let count: Int?
    public let items: [HomeTicketCard]?
}

public struct HomeQueueDefinition: Codable, Sendable, Equatable {
    public let key: String?
    public let title: String?
    public let description: String?
    public let icon: String?
}

public struct HomeTicketCard: Codable, Sendable, Equatable, Identifiable, Hashable {
    public let id: Int
    public let hash: String?
    public let code: String?
    public let title: String
    public let descriptionPreview: String?
    public let status: TicketStatus?
    public let priority: TicketPriority?
    public let client: TicketClient?
    public let requester: String?
    public let assignee: String?
    public let source: String?
    public let tags: [String]?
    public let dueDate: String?
    public let createdAt: String?
    public let updatedAt: String?
    public let url: String?
}

public struct HomeTimer: Codable, Sendable, Equatable, Identifiable, Hashable {
    public let entryId: Int
    public let ticketId: Int
    public let ticketHash: String?
    public let ticketTitle: String
    public let startedAt: String?
    public let isPaused: Bool?
    public let elapsedMinutes: Int
    public let elapsedLabel: String?
    public let url: String?

    public var id: Int {
        entryId
    }
}

public struct HomeNotificationSummary: Codable, Sendable, Equatable {
    public let unreadCount: Int?
    public let items: [AppNotificationItem]?
}

public struct HomeTimeActivity: Codable, Sendable, Equatable {
    public let period: HomeTimePeriod?
    public let totals: [String: HomeTimeTotal]?
    public let entries: [HomeTimeEntry]?
    public let team: [HomeTeamTimeMember]?
    public let chart: HomeTimeChart?
}

public struct HomeTimePeriod: Codable, Sendable, Equatable {
    public let key: String?
    public let label: String?
    public let start: String?
    public let end: String?
}

public struct HomeTimeTotal: Codable, Sendable, Equatable, Hashable {
    public let minutes: Int?
    public let label: String?
}

public struct HomeTimeEntry: Codable, Sendable, Equatable, Identifiable, Hashable {
    public let id: Int
    public let ticketId: Int
    public let ticketHash: String?
    public let ticketCode: String?
    public let ticketTitle: String
    public let clientName: String?
    public let statusName: String?
    public let summary: String?
    public let startedAt: String?
    public let endedAt: String?
    public let minutes: Int
    public let minutesLabel: String?
    public let url: String?
}

public struct HomeTeamTimeMember: Codable, Sendable, Equatable, Identifiable, Hashable {
    public let userId: Int
    public let name: String
    public let email: String?
    public let role: String?
    public let avatar: String?
    public let isRunning: Bool?
    public let totals: [String: HomeTimeTotal]?
    public let entries: [HomeTimeEntry]?
    public let latestEntry: HomeTimeEntry?

    public var id: Int {
        userId
    }
}

public struct HomeTimeChart: Codable, Sendable, Equatable {
    public let days: [HomeTimeChartDay]?
    public let maxMinutes: Int?
    public let totalMinutes: Int?
    public let totalLabel: String?
}

public struct HomeTimeChartDay: Codable, Sendable, Equatable, Identifiable, Hashable {
    public let key: String
    public let label: String?
    public let fullLabel: String?
    public let minutes: Int
    public let minutesLabel: String?
    public let users: [HomeTimeChartUser]?

    public var id: String {
        key
    }
}

public struct HomeTimeChartUser: Codable, Sendable, Equatable, Identifiable, Hashable {
    public let userId: Int
    public let name: String
    public let minutes: Int
    public let minutesLabel: String?

    public var id: Int {
        userId
    }
}
