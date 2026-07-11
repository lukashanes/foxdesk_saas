import Foundation

public actor LocalSessionDataStore {
    private let cacheDirectoryURL: URL
    private let legacyDefaults: UserDefaults
    private let managedPrefixes = [
        "net.foxdesk.ios.home-feed-cache.",
        "net.foxdesk.ios.ticket-list-cache.",
        "net.foxdesk.ios.ticket-detail-cache.",
        "net.foxdesk.ios.ticket-comment-draft."
    ]

    public init(
        cacheDirectoryURL: URL = ProtectedLocalCache.defaultRootURL,
        legacyDefaults: UserDefaults = .standard
    ) {
        self.cacheDirectoryURL = cacheDirectoryURL
        self.legacyDefaults = legacyDefaults
    }

    public func clear(userId: Int) {
        ProtectedLocalCache.clear(userId: userId, rootURL: cacheDirectoryURL)
        let userMarker = ".user-\(userId)."
        for key in legacyDefaults.dictionaryRepresentation().keys
            where managedPrefixes.contains(where: key.hasPrefix) && key.contains(userMarker) {
            legacyDefaults.removeObject(forKey: key)
        }
    }

    public func clearAll() {
        ProtectedLocalCache.clearAll(rootURL: cacheDirectoryURL)
        for key in legacyDefaults.dictionaryRepresentation().keys
            where managedPrefixes.contains(where: key.hasPrefix) {
            legacyDefaults.removeObject(forKey: key)
        }
    }
}
