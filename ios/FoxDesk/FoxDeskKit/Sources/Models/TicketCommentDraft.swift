import Foundation

public struct TicketCommentDraft: Codable, Equatable, Sendable {
    public let ticketId: Int
    public let userId: Int
    public var content: String
    public var isInternal: Bool
    public var includeTime: Bool
    public var useExactTime: Bool
    public var durationMinutes: Int
    public var workDate: Date
    public var startTime: Date
    public var endTime: Date
    public var updatedAt: Date

    public init(
        ticketId: Int,
        userId: Int,
        content: String,
        isInternal: Bool = false,
        includeTime: Bool = false,
        useExactTime: Bool = false,
        durationMinutes: Int = 15,
        workDate: Date = Date(),
        startTime: Date = Date().addingTimeInterval(-15 * 60),
        endTime: Date = Date(),
        updatedAt: Date = Date()
    ) {
        self.ticketId = ticketId
        self.userId = userId
        self.content = content
        self.isInternal = isInternal
        self.includeTime = includeTime
        self.useExactTime = useExactTime
        self.durationMinutes = durationMinutes
        self.workDate = workDate
        self.startTime = startTime
        self.endTime = endTime
        self.updatedAt = updatedAt
    }
}

public actor TicketCommentDraftStore {
    private let defaults: UserDefaults
    private let encoder: JSONEncoder
    private let decoder: JSONDecoder
    private let keyPrefix = "net.foxdesk.ios.ticket-comment-draft"

    public init(defaults: UserDefaults = .standard) {
        self.defaults = defaults
        let encoder = JSONEncoder()
        encoder.dateEncodingStrategy = .iso8601
        self.encoder = encoder

        let decoder = JSONDecoder()
        decoder.dateDecodingStrategy = .iso8601
        self.decoder = decoder
    }

    public func load(ticketId: Int, userId: Int) throws -> TicketCommentDraft? {
        guard let data = defaults.data(forKey: key(ticketId: ticketId, userId: userId)) else {
            return nil
        }
        return try decoder.decode(TicketCommentDraft.self, from: data)
    }

    public func save(_ draft: TicketCommentDraft) throws {
        let trimmedContent = draft.content.trimmingCharacters(in: .whitespacesAndNewlines)
        guard !trimmedContent.isEmpty else {
            clear(ticketId: draft.ticketId, userId: draft.userId)
            return
        }

        var stored = draft
        stored.updatedAt = Date()
        let data = try encoder.encode(stored)
        defaults.set(data, forKey: key(ticketId: draft.ticketId, userId: draft.userId))
    }

    public func clear(ticketId: Int, userId: Int) {
        defaults.removeObject(forKey: key(ticketId: ticketId, userId: userId))
    }

    private func key(ticketId: Int, userId: Int) -> String {
        "\(keyPrefix).user-\(userId).ticket-\(ticketId)"
    }
}
