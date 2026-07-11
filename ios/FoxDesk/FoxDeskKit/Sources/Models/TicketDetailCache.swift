import Foundation

public struct CachedTicketDetail: Codable, Equatable, Sendable {
    public let userId: Int
    public let tenantId: Int
    public let ticketId: Int
    public let detail: TicketDetailPayload
    public let cachedAt: Date

    public init(
        userId: Int,
        tenantId: Int,
        ticketId: Int,
        detail: TicketDetailPayload,
        cachedAt: Date = Date()
    ) {
        self.userId = userId
        self.tenantId = tenantId
        self.ticketId = ticketId
        self.detail = detail
        self.cachedAt = cachedAt
    }
}

public actor TicketDetailCacheStore {
    private let directoryURL: URL
    private let legacyDefaults: UserDefaults
    private let maxAge: TimeInterval
    private let encoder: JSONEncoder
    private let decoder: JSONDecoder
    private let keyPrefix = "net.foxdesk.ios.ticket-detail-cache"

    public init(
        directoryURL: URL = ProtectedLocalCache.defaultRootURL,
        legacyDefaults: UserDefaults = .standard,
        maxAge: TimeInterval = 7 * 24 * 60 * 60
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

    public func load(userId: Int, tenantId: Int, ticketId: Int) throws -> CachedTicketDetail? {
        let legacyKey = key(userId: userId, tenantId: tenantId, ticketId: ticketId)
        let fileURL = fileURL(userId: userId, tenantId: tenantId, ticketId: ticketId)
        guard let data = try ProtectedLocalCache.read(from: fileURL)
            ?? migrateLegacyData(forKey: legacyKey, to: fileURL) else {
            return nil
        }
        do {
            let cached = try decoder.decode(CachedTicketDetail.self, from: data)
            guard !ProtectedLocalCache.isExpired(cached.cachedAt, maxAge: maxAge) else {
                clear(userId: userId, tenantId: tenantId, ticketId: ticketId)
                return nil
            }
            return cached
        } catch {
            clear(userId: userId, tenantId: tenantId, ticketId: ticketId)
            return nil
        }
    }

    public func save(userId: Int, tenantId: Int, ticketId: Int, detail: TicketDetailPayload) throws {
        let cached = CachedTicketDetail(userId: userId, tenantId: tenantId, ticketId: ticketId, detail: detail)
        let data = try encoder.encode(cached)
        try ProtectedLocalCache.write(
            data,
            to: fileURL(userId: userId, tenantId: tenantId, ticketId: ticketId),
            rootURL: directoryURL
        )
        legacyDefaults.removeObject(forKey: key(userId: userId, tenantId: tenantId, ticketId: ticketId))
    }

    public func clear(userId: Int, tenantId: Int, ticketId: Int) {
        ProtectedLocalCache.remove(fileURL(userId: userId, tenantId: tenantId, ticketId: ticketId))
        legacyDefaults.removeObject(forKey: key(userId: userId, tenantId: tenantId, ticketId: ticketId))
    }

    private func key(userId: Int, tenantId: Int, ticketId: Int) -> String {
        "\(keyPrefix).user-\(userId).tenant-\(tenantId).ticket-\(ticketId)"
    }

    private func fileURL(userId: Int, tenantId: Int, ticketId: Int) -> URL {
        ProtectedLocalCache.fileURL(
            rootURL: directoryURL,
            userId: userId,
            category: "ticket-details",
            key: "tenant-\(tenantId)-ticket-\(ticketId)"
        )
    }

    private func migrateLegacyData(forKey key: String, to fileURL: URL) throws -> Data? {
        guard let data = legacyDefaults.data(forKey: key) else { return nil }
        try ProtectedLocalCache.write(data, to: fileURL, rootURL: directoryURL)
        legacyDefaults.removeObject(forKey: key)
        return data
    }
}
