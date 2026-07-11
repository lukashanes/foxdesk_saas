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
    private let directoryURL: URL
    private let legacyDefaults: UserDefaults
    private let maxAge: TimeInterval
    private let encoder: JSONEncoder
    private let decoder: JSONDecoder
    private let keyPrefix = "net.foxdesk.ios.ticket-comment-draft"

    public init(
        directoryURL: URL = ProtectedLocalCache.defaultRootURL,
        legacyDefaults: UserDefaults = .standard,
        maxAge: TimeInterval = 30 * 24 * 60 * 60
    ) {
        self.directoryURL = directoryURL
        self.legacyDefaults = legacyDefaults
        self.maxAge = maxAge
        let encoder = JSONEncoder()
        encoder.dateEncodingStrategy = .iso8601
        self.encoder = encoder

        let decoder = JSONDecoder()
        decoder.dateDecodingStrategy = .iso8601
        self.decoder = decoder
    }

    public func load(ticketId: Int, userId: Int) throws -> TicketCommentDraft? {
        let legacyKey = key(ticketId: ticketId, userId: userId)
        let fileURL = fileURL(ticketId: ticketId, userId: userId)
        guard let data = try ProtectedLocalCache.read(from: fileURL)
            ?? migrateLegacyData(forKey: legacyKey, to: fileURL) else {
            return nil
        }
        do {
            let draft = try decoder.decode(TicketCommentDraft.self, from: data)
            guard !ProtectedLocalCache.isExpired(draft.updatedAt, maxAge: maxAge) else {
                clear(ticketId: ticketId, userId: userId)
                return nil
            }
            return draft
        } catch {
            clear(ticketId: ticketId, userId: userId)
            return nil
        }
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
        try ProtectedLocalCache.write(
            data,
            to: fileURL(ticketId: draft.ticketId, userId: draft.userId),
            rootURL: directoryURL
        )
        legacyDefaults.removeObject(forKey: key(ticketId: draft.ticketId, userId: draft.userId))
    }

    public func clear(ticketId: Int, userId: Int) {
        ProtectedLocalCache.remove(fileURL(ticketId: ticketId, userId: userId))
        legacyDefaults.removeObject(forKey: key(ticketId: ticketId, userId: userId))
    }

    private func key(ticketId: Int, userId: Int) -> String {
        "\(keyPrefix).user-\(userId).ticket-\(ticketId)"
    }

    private func fileURL(ticketId: Int, userId: Int) -> URL {
        ProtectedLocalCache.fileURL(
            rootURL: directoryURL,
            userId: userId,
            category: "comment-drafts",
            key: "ticket-\(ticketId)"
        )
    }

    private func migrateLegacyData(forKey key: String, to fileURL: URL) throws -> Data? {
        guard let data = legacyDefaults.data(forKey: key) else { return nil }
        try ProtectedLocalCache.write(data, to: fileURL, rootURL: directoryURL)
        legacyDefaults.removeObject(forKey: key)
        return data
    }
}
