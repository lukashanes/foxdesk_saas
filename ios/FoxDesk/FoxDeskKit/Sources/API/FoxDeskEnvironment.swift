import Foundation

public struct FoxDeskEnvironment: Sendable, Hashable {
    public let baseURL: URL

    public init(baseURL: URL) {
        self.baseURL = baseURL
    }

    public static let production = FoxDeskEnvironment(
        baseURL: URL(string: "https://app.foxdesk.net/index.php")!
    )

    public static let staging = FoxDeskEnvironment(
        baseURL: URL(string: "https://staging.app.foxdesk.net/index.php")!
    )
}

