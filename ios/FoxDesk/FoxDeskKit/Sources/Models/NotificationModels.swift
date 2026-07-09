import Foundation

public struct NotificationsPayload: Decodable, Sendable {
    public let unreadCount: Int
    public let items: [AppNotificationItem]
    public let pagination: Pagination?
}

public struct AppNotificationItem: Codable, Sendable, Equatable, Identifiable, Hashable {
    public let id: Int
    public let type: String
    public let ticketId: Int?
    public let ticketHash: String?
    public let isRead: Bool
    public let isResolved: Bool
    public let createdAt: String?
    public let timeAgo: String?
    public let text: String?
    public let actionText: String?
    public let snippet: String?
    public let isAction: Bool?
    public let actor: NotificationActor?

    public init(
        id: Int,
        type: String,
        ticketId: Int?,
        ticketHash: String? = nil,
        isRead: Bool,
        isResolved: Bool,
        createdAt: String?,
        timeAgo: String?,
        text: String?,
        actionText: String?,
        snippet: String?,
        isAction: Bool?,
        actor: NotificationActor?
    ) {
        self.id = id
        self.type = type
        self.ticketId = ticketId
        self.ticketHash = ticketHash
        self.isRead = isRead
        self.isResolved = isResolved
        self.createdAt = createdAt
        self.timeAgo = timeAgo
        self.text = text
        self.actionText = actionText
        self.snippet = snippet
        self.isAction = isAction
        self.actor = actor
    }
}

public struct NotificationActor: Codable, Sendable, Equatable, Hashable {
    public let name: String?
    public let email: String?
    public let avatar: String?
}

public struct NotificationReadStateRequest: Encodable, Sendable {
    public let scope: String
    public let notificationId: Int?
    public let ticketId: Int?
    public let isRead: Bool?

    public init(
        scope: String,
        notificationId: Int? = nil,
        ticketId: Int? = nil,
        isRead: Bool? = nil
    ) {
        self.scope = scope
        self.notificationId = notificationId
        self.ticketId = ticketId
        self.isRead = isRead
    }
}

public struct NotificationReadStatePayload: Decodable, Sendable, Equatable {
    public let unreadCount: Int
    public let updated: Bool
}
