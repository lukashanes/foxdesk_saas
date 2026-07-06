import Foundation

public struct MobileAuthResponse: Decodable, Sendable {
    public let success: Bool?
    public let requires2fa: Bool
    public let challengeToken: String?
    public let expiresIn: Int?
    public let session: MobileSessionTokens?
    public let user: FoxDeskUser?
    public let appShell: JSONValue?
    public let home: JSONValue?

    private enum CodingKeys: String, CodingKey {
        case success
        case requires2Fa
        case challengeToken
        case expiresIn
        case session
        case user
        case appShell
        case home
    }

    public init(from decoder: Decoder) throws {
        let container = try decoder.container(keyedBy: CodingKeys.self)
        self.success = try container.decodeIfPresent(Bool.self, forKey: .success)
        self.requires2fa = try container.decode(Bool.self, forKey: .requires2Fa)
        self.challengeToken = try container.decodeIfPresent(String.self, forKey: .challengeToken)
        self.expiresIn = try container.decodeIfPresent(Int.self, forKey: .expiresIn)
        self.session = try container.decodeIfPresent(MobileSessionTokens.self, forKey: .session)
        self.user = try container.decodeIfPresent(FoxDeskUser.self, forKey: .user)
        self.appShell = try container.decodeIfPresent(JSONValue.self, forKey: .appShell)
        self.home = try container.decodeIfPresent(JSONValue.self, forKey: .home)
    }
}

public struct MobileMeResponse: Decodable, Sendable {
    public let success: Bool?
    public let user: FoxDeskUser
    public let appShell: JSONValue?
    public let home: JSONValue?
}

public struct FoxDeskUser: Codable, Sendable, Equatable, Identifiable {
    public let id: Int
    public let email: String
    public let firstName: String?
    public let lastName: String?
    public let name: String
    public let role: String
    public let language: String?
    public let tenantId: Int?
    public let avatar: String?
}

public struct MobileSessionTokens: Codable, Sendable, Equatable {
    public let tokenType: String
    public let accessToken: String
    public let refreshToken: String
    public let expiresIn: Int
    public let refreshExpiresIn: Int
}

public struct MobileLoginRequest: Encodable, Sendable {
    public let email: String
    public let password: String
    public let deviceId: String
    public let deviceName: String
    public let appVersion: String
}

public struct MobileTwoFactorRequest: Encodable, Sendable {
    public let challengeToken: String
    public let code: String
    public let deviceId: String
    public let deviceName: String
    public let appVersion: String
}

public struct MobileRefreshRequest: Encodable, Sendable {
    public let refreshToken: String
    public let deviceId: String
    public let deviceName: String
    public let appVersion: String
}

public struct LogoutRequest: Encodable, Sendable {
    public let refreshToken: String?
}

public struct LogoutResponse: Decodable, Sendable, Equatable {
    public let success: Bool?
    public let loggedOut: Bool
}

public enum APNsEnvironment: String, Sendable {
    case sandbox
    case production
}

public struct DeviceRegistrationRequest: Encodable, Sendable {
    public let apnsDeviceToken: String
    public let apnsEnvironment: String
    public let deviceId: String
    public let deviceName: String
    public let appVersion: String
}

public struct DeviceRegistrationResponse: Decodable, Sendable, Equatable {
    public let success: Bool?
    public let deviceId: Int
    public let registered: Bool
}

public struct DeviceUnregisterRequest: Encodable, Sendable {
    public let deviceId: String
}

public struct DeviceUnregisterResponse: Decodable, Sendable, Equatable {
    public let success: Bool?
    public let unregistered: Bool
}
