import Foundation

public struct CachedTicketDetail: Codable, Equatable, Sendable {
    public let userId: Int
    public let ticketId: Int
    public let detail: TicketDetailPayload
    public let cachedAt: Date

    public init(
        userId: Int,
        ticketId: Int,
        detail: TicketDetailPayload,
        cachedAt: Date = Date()
    ) {
        self.userId = userId
        self.ticketId = ticketId
        self.detail = detail
        self.cachedAt = cachedAt
    }
}

public actor TicketDetailCacheStore {
    private let defaults: UserDefaults
    private let encoder: JSONEncoder
    private let decoder: JSONDecoder
    private let keyPrefix = "net.foxdesk.ios.ticket-detail-cache"

    public init(defaults: UserDefaults = .standard) {
        self.defaults = defaults

        let encoder = JSONEncoder()
        encoder.dateEncodingStrategy = .iso8601
        self.encoder = encoder

        let decoder = JSONDecoder()
        decoder.dateDecodingStrategy = .iso8601
        self.decoder = decoder
    }

    public func load(userId: Int, ticketId: Int) throws -> CachedTicketDetail? {
        guard let data = defaults.data(forKey: key(userId: userId, ticketId: ticketId)) else {
            return nil
        }
        return try decoder.decode(CachedTicketDetail.self, from: data)
    }

    public func save(userId: Int, ticketId: Int, detail: TicketDetailPayload) throws {
        let cached = CachedTicketDetail(userId: userId, ticketId: ticketId, detail: detail)
        let data = try encoder.encode(cached)
        defaults.set(data, forKey: key(userId: userId, ticketId: ticketId))
    }

    public func clear(userId: Int, ticketId: Int) {
        defaults.removeObject(forKey: key(userId: userId, ticketId: ticketId))
    }

    private func key(userId: Int, ticketId: Int) -> String {
        "\(keyPrefix).user-\(userId).ticket-\(ticketId)"
    }
}
