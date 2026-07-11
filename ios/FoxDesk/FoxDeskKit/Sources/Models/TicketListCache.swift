import Foundation

public struct CachedTicketList: Codable, Equatable, Sendable {
    public let userId: Int
    public let tenantId: Int
    public let listKey: String
    public let tickets: [TicketSummary]
    public let totalCount: Int?
    public let cachedAt: Date

    public init(
        userId: Int,
        tenantId: Int,
        listKey: String,
        tickets: [TicketSummary],
        totalCount: Int?,
        cachedAt: Date = Date()
    ) {
        self.userId = userId
        self.tenantId = tenantId
        self.listKey = listKey
        self.tickets = tickets
        self.totalCount = totalCount
        self.cachedAt = cachedAt
    }
}

public actor TicketListCacheStore {
    private let directoryURL: URL
    private let legacyDefaults: UserDefaults
    private let maxAge: TimeInterval
    private let encoder: JSONEncoder
    private let decoder: JSONDecoder
    private let keyPrefix = "net.foxdesk.ios.ticket-list-cache"

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

    public func load(userId: Int, tenantId: Int, listKey: String) throws -> CachedTicketList? {
        let legacyKey = key(userId: userId, tenantId: tenantId, listKey: listKey)
        let fileURL = fileURL(userId: userId, tenantId: tenantId, listKey: listKey)
        guard let data = try ProtectedLocalCache.read(from: fileURL)
            ?? migrateLegacyData(forKey: legacyKey, to: fileURL) else {
            return nil
        }
        do {
            let cached = try decoder.decode(CachedTicketList.self, from: data)
            guard !ProtectedLocalCache.isExpired(cached.cachedAt, maxAge: maxAge) else {
                clear(userId: userId, tenantId: tenantId, listKey: listKey)
                return nil
            }
            return cached
        } catch {
            clear(userId: userId, tenantId: tenantId, listKey: listKey)
            return nil
        }
    }

    public func save(userId: Int, tenantId: Int, listKey: String, tickets: [TicketSummary], totalCount: Int?) throws {
        let cached = CachedTicketList(
            userId: userId,
            tenantId: tenantId,
            listKey: listKey,
            tickets: tickets,
            totalCount: totalCount
        )
        let data = try encoder.encode(cached)
        try ProtectedLocalCache.write(
            data,
            to: fileURL(userId: userId, tenantId: tenantId, listKey: listKey),
            rootURL: directoryURL
        )
        legacyDefaults.removeObject(forKey: key(userId: userId, tenantId: tenantId, listKey: listKey))
    }

    public func clear(userId: Int, tenantId: Int, listKey: String) {
        ProtectedLocalCache.remove(fileURL(userId: userId, tenantId: tenantId, listKey: listKey))
        legacyDefaults.removeObject(forKey: key(userId: userId, tenantId: tenantId, listKey: listKey))
    }

    private func key(userId: Int, tenantId: Int, listKey: String) -> String {
        let allowed = CharacterSet.alphanumerics.union(CharacterSet(charactersIn: "-_"))
        let safeListKey = listKey.unicodeScalars.map { scalar in
            allowed.contains(scalar) ? String(scalar) : "-"
        }.joined()
        return "\(keyPrefix).user-\(userId).tenant-\(tenantId).\(safeListKey)"
    }

    private func fileURL(userId: Int, tenantId: Int, listKey: String) -> URL {
        ProtectedLocalCache.fileURL(
            rootURL: directoryURL,
            userId: userId,
            category: "ticket-lists",
            key: "tenant-\(tenantId)-\(listKey)"
        )
    }

    private func migrateLegacyData(forKey key: String, to fileURL: URL) throws -> Data? {
        guard let data = legacyDefaults.data(forKey: key) else { return nil }
        try ProtectedLocalCache.write(data, to: fileURL, rootURL: directoryURL)
        legacyDefaults.removeObject(forKey: key)
        return data
    }
}
