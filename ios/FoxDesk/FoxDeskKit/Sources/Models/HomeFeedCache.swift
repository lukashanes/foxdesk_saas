import Foundation

public struct CachedHomeFeed: Codable, Equatable, Sendable {
    public let userId: Int
    public let tenantId: Int
    public let home: HomeFeed
    public let cachedAt: Date

    public init(userId: Int, tenantId: Int, home: HomeFeed, cachedAt: Date = Date()) {
        self.userId = userId
        self.tenantId = tenantId
        self.home = home
        self.cachedAt = cachedAt
    }
}

public actor HomeFeedCacheStore {
    private let directoryURL: URL
    private let legacyDefaults: UserDefaults
    private let maxAge: TimeInterval
    private let encoder: JSONEncoder
    private let decoder: JSONDecoder
    private let keyPrefix = "net.foxdesk.ios.home-feed-cache"

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

    public func load(userId: Int, tenantId: Int) throws -> CachedHomeFeed? {
        let legacyKey = key(userId: userId, tenantId: tenantId)
        let fileURL = fileURL(userId: userId, tenantId: tenantId)
        guard let data = try ProtectedLocalCache.read(from: fileURL)
            ?? migrateLegacyData(forKey: legacyKey, to: fileURL) else {
            return nil
        }
        do {
            let cached = try decoder.decode(CachedHomeFeed.self, from: data)
            guard !ProtectedLocalCache.isExpired(cached.cachedAt, maxAge: maxAge) else {
                clear(userId: userId, tenantId: tenantId)
                return nil
            }
            return cached
        } catch {
            clear(userId: userId, tenantId: tenantId)
            return nil
        }
    }

    public func save(userId: Int, tenantId: Int, home: HomeFeed) throws {
        let cached = CachedHomeFeed(userId: userId, tenantId: tenantId, home: home)
        let data = try encoder.encode(cached)
        try ProtectedLocalCache.write(data, to: fileURL(userId: userId, tenantId: tenantId), rootURL: directoryURL)
        legacyDefaults.removeObject(forKey: key(userId: userId, tenantId: tenantId))
    }

    public func clear(userId: Int, tenantId: Int) {
        ProtectedLocalCache.remove(fileURL(userId: userId, tenantId: tenantId))
        legacyDefaults.removeObject(forKey: key(userId: userId, tenantId: tenantId))
    }

    private func key(userId: Int, tenantId: Int) -> String {
        "\(keyPrefix).user-\(userId).tenant-\(tenantId)"
    }

    private func fileURL(userId: Int, tenantId: Int) -> URL {
        ProtectedLocalCache.fileURL(
            rootURL: directoryURL,
            userId: userId,
            category: "home-feed",
            key: "tenant-\(tenantId)"
        )
    }

    private func migrateLegacyData(forKey key: String, to fileURL: URL) throws -> Data? {
        guard let data = legacyDefaults.data(forKey: key) else { return nil }
        try ProtectedLocalCache.write(data, to: fileURL, rootURL: directoryURL)
        legacyDefaults.removeObject(forKey: key)
        return data
    }
}
