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
    private let defaults: UserDefaults
    private let encoder: JSONEncoder
    private let decoder: JSONDecoder
    private let keyPrefix = "net.foxdesk.ios.home-feed-cache"

    public init(defaults: UserDefaults = .standard) {
        self.defaults = defaults

        let encoder = JSONEncoder()
        encoder.dateEncodingStrategy = .iso8601
        self.encoder = encoder

        let decoder = JSONDecoder()
        decoder.dateDecodingStrategy = .iso8601
        self.decoder = decoder
    }

    public func load(userId: Int, tenantId: Int) throws -> CachedHomeFeed? {
        guard let data = defaults.data(forKey: key(userId: userId, tenantId: tenantId)) else {
            return nil
        }
        return try decoder.decode(CachedHomeFeed.self, from: data)
    }

    public func save(userId: Int, tenantId: Int, home: HomeFeed) throws {
        let cached = CachedHomeFeed(userId: userId, tenantId: tenantId, home: home)
        let data = try encoder.encode(cached)
        defaults.set(data, forKey: key(userId: userId, tenantId: tenantId))
    }

    public func clear(userId: Int, tenantId: Int) {
        defaults.removeObject(forKey: key(userId: userId, tenantId: tenantId))
    }

    private func key(userId: Int, tenantId: Int) -> String {
        "\(keyPrefix).user-\(userId).tenant-\(tenantId)"
    }
}
