import Foundation

public struct CachedTicketList: Codable, Equatable, Sendable {
    public let userId: Int
    public let listKey: String
    public let tickets: [TicketSummary]
    public let totalCount: Int?
    public let cachedAt: Date

    public init(
        userId: Int,
        listKey: String,
        tickets: [TicketSummary],
        totalCount: Int?,
        cachedAt: Date = Date()
    ) {
        self.userId = userId
        self.listKey = listKey
        self.tickets = tickets
        self.totalCount = totalCount
        self.cachedAt = cachedAt
    }
}

public actor TicketListCacheStore {
    private let defaults: UserDefaults
    private let encoder: JSONEncoder
    private let decoder: JSONDecoder
    private let keyPrefix = "net.foxdesk.ios.ticket-list-cache"

    public init(defaults: UserDefaults = .standard) {
        self.defaults = defaults

        let encoder = JSONEncoder()
        encoder.dateEncodingStrategy = .iso8601
        self.encoder = encoder

        let decoder = JSONDecoder()
        decoder.dateDecodingStrategy = .iso8601
        self.decoder = decoder
    }

    public func load(userId: Int, listKey: String) throws -> CachedTicketList? {
        guard let data = defaults.data(forKey: key(userId: userId, listKey: listKey)) else {
            return nil
        }
        return try decoder.decode(CachedTicketList.self, from: data)
    }

    public func save(userId: Int, listKey: String, tickets: [TicketSummary], totalCount: Int?) throws {
        let cached = CachedTicketList(
            userId: userId,
            listKey: listKey,
            tickets: tickets,
            totalCount: totalCount
        )
        let data = try encoder.encode(cached)
        defaults.set(data, forKey: key(userId: userId, listKey: listKey))
    }

    public func clear(userId: Int, listKey: String) {
        defaults.removeObject(forKey: key(userId: userId, listKey: listKey))
    }

    private func key(userId: Int, listKey: String) -> String {
        let allowed = CharacterSet.alphanumerics.union(CharacterSet(charactersIn: "-_"))
        let safeListKey = listKey.unicodeScalars.map { scalar in
            allowed.contains(scalar) ? String(scalar) : "-"
        }.joined()
        return "\(keyPrefix).user-\(userId).\(safeListKey)"
    }
}
