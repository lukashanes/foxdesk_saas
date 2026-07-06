import Foundation

public struct APIErrorResponse: Decodable, Equatable {
    public let success: Bool?
    public let error: String?
    public let message: String?
}

public struct AppEnvelope<DataPayload: Decodable & Sendable>: Decodable, Sendable {
    public let success: Bool?
    public let data: DataPayload
    public let meta: AppMeta?
    public let errors: [APIMessage]?
}

public struct AppMeta: Decodable, Sendable, Equatable {
    public let schemaVersion: Int?
    public let generatedAt: String?
    public let resource: String?
}

public struct APIMessage: Decodable, Sendable, Equatable {
    public let message: String?
    public let code: String?
}

public enum JSONValue: Decodable, Sendable, Equatable {
    case string(String)
    case number(Double)
    case bool(Bool)
    case object([String: JSONValue])
    case array([JSONValue])
    case null

    public init(from decoder: Decoder) throws {
        let container = try decoder.singleValueContainer()
        if container.decodeNil() {
            self = .null
        } else if let value = try? container.decode(Bool.self) {
            self = .bool(value)
        } else if let value = try? container.decode(Double.self) {
            self = .number(value)
        } else if let value = try? container.decode(String.self) {
            self = .string(value)
        } else if let value = try? container.decode([JSONValue].self) {
            self = .array(value)
        } else {
            self = .object(try container.decode([String: JSONValue].self))
        }
    }
}

public struct DeviceContext: Sendable, Equatable {
    public let deviceId: String
    public let deviceName: String
    public let appVersion: String

    public init(deviceId: String, deviceName: String, appVersion: String) {
        self.deviceId = deviceId
        self.deviceName = deviceName
        self.appVersion = appVersion
    }
}

