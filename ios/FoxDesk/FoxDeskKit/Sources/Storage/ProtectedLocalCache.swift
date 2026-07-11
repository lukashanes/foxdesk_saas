import CryptoKit
import Foundation

public enum ProtectedLocalCache {
    public static var defaultRootURL: URL {
        let applicationSupport = FileManager.default.urls(
            for: .applicationSupportDirectory,
            in: .userDomainMask
        ).first!
        return applicationSupport
            .appendingPathComponent("FoxDesk", isDirectory: true)
            .appendingPathComponent("ProtectedCache", isDirectory: true)
    }

    static func fileURL(
        rootURL: URL,
        userId: Int,
        category: String,
        key: String
    ) -> URL {
        rootURL
            .appendingPathComponent("user-\(userId)", isDirectory: true)
            .appendingPathComponent(category, isDirectory: true)
            .appendingPathComponent("\(digest(key)).json", isDirectory: false)
    }

    static func read(from fileURL: URL) throws -> Data? {
        guard FileManager.default.fileExists(atPath: fileURL.path) else {
            return nil
        }
        return try Data(contentsOf: fileURL)
    }

    static func write(_ data: Data, to fileURL: URL, rootURL: URL) throws {
        try prepareDirectory(fileURL.deletingLastPathComponent(), rootURL: rootURL)
        try data.write(to: fileURL, options: [.atomic, .completeFileProtection])
        try FileManager.default.setAttributes(
            [.protectionKey: FileProtectionType.complete],
            ofItemAtPath: fileURL.path
        )
    }

    static func remove(_ fileURL: URL) {
        try? FileManager.default.removeItem(at: fileURL)
    }

    static func clear(userId: Int, rootURL: URL) {
        remove(rootURL.appendingPathComponent("user-\(userId)", isDirectory: true))
    }

    static func clearAll(rootURL: URL) {
        remove(rootURL)
    }

    static func isExpired(_ date: Date, maxAge: TimeInterval, now: Date = Date()) -> Bool {
        maxAge < 0 || now.timeIntervalSince(date) > maxAge
    }

    private static func prepareDirectory(_ directoryURL: URL, rootURL: URL) throws {
        try FileManager.default.createDirectory(
            at: directoryURL,
            withIntermediateDirectories: true,
            attributes: [.protectionKey: FileProtectionType.complete]
        )

        var rootValues = URLResourceValues()
        rootValues.isExcludedFromBackup = true
        var mutableRootURL = rootURL
        try? mutableRootURL.setResourceValues(rootValues)
    }

    private static func digest(_ key: String) -> String {
        SHA256.hash(data: Data(key.utf8))
            .map { String(format: "%02x", $0) }
            .joined()
    }
}
