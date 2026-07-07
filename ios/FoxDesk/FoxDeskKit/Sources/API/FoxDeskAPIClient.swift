import Foundation

public enum FoxDeskAPIError: Error, Equatable, LocalizedError {
    case invalidURL
    case invalidResponse
    case unauthorized
    case server(statusCode: Int, message: String)
    case decoding(String)

    public var errorDescription: String? {
        switch self {
        case .invalidURL:
            return "The FoxDesk API URL is invalid."
        case .invalidResponse:
            return "FoxDesk returned an invalid response."
        case .unauthorized:
            return "Sign in again to continue."
        case .server(_, let message):
            return message
        case .decoding(let message):
            return message
        }
    }
}

public final class FoxDeskAPIClient {
    public let environment: FoxDeskEnvironment

    private let session: URLSession
    private let encoder: JSONEncoder
    private let decoder: JSONDecoder

    public init(
        environment: FoxDeskEnvironment = .production,
        session: URLSession = FoxDeskAPIClient.makeDefaultSession()
    ) {
        self.environment = environment
        self.session = session

        let encoder = JSONEncoder()
        encoder.keyEncodingStrategy = .convertToSnakeCase
        self.encoder = encoder

        let decoder = JSONDecoder()
        decoder.keyDecodingStrategy = .convertFromSnakeCase
        self.decoder = decoder
    }

    public static func makeDefaultSession() -> URLSession {
        let configuration = URLSessionConfiguration.ephemeral
        configuration.httpCookieStorage = nil
        configuration.httpShouldSetCookies = false
        configuration.urlCache = nil
        configuration.requestCachePolicy = .reloadIgnoringLocalCacheData
        return URLSession(configuration: configuration)
    }

    public func apiURL(path: String, queryItems: [URLQueryItem] = []) throws -> URL {
        guard var components = URLComponents(url: environment.baseURL, resolvingAgainstBaseURL: false) else {
            throw FoxDeskAPIError.invalidURL
        }

        var items = components.queryItems ?? []
        items.append(contentsOf: queryItems)
        components.path = nativeAPIPath(path)
        components.queryItems = items

        guard let url = components.url else {
            throw FoxDeskAPIError.invalidURL
        }
        return url
    }

    private func nativeAPIPath(_ path: String) -> String {
        let basePath = environment.baseURL.path
        let rawRootPath: String
        if basePath.hasSuffix("/index.php") {
            rawRootPath = String(basePath.dropLast("/index.php".count))
        } else if basePath == "/" {
            rawRootPath = ""
        } else {
            rawRootPath = basePath
        }

        let trimmedRoot = rawRootPath.trimmingCharacters(in: CharacterSet(charactersIn: "/"))
        let rootPath = trimmedRoot.isEmpty ? "" : "/" + trimmedRoot
        return rootPath + "/api/mobile/v1/" + path.trimmingCharacters(in: CharacterSet(charactersIn: "/"))
    }

    private func legacyAPIURL(path: String, method: String, queryItems: [URLQueryItem] = []) throws -> URL? {
        guard let route = legacyMobileRoute(path: path, method: method),
              var components = URLComponents(url: environment.baseURL, resolvingAgainstBaseURL: false)
        else {
            return nil
        }

        let basePath = environment.baseURL.path
        if basePath.hasSuffix("/index.php") {
            components.path = basePath
        } else {
            let trimmed = basePath.trimmingCharacters(in: CharacterSet(charactersIn: "/"))
            components.path = (trimmed.isEmpty ? "" : "/" + trimmed) + "/index.php"
        }

        var items = components.queryItems ?? []
        items.append(URLQueryItem(name: "page", value: "api"))
        items.append(URLQueryItem(name: "action", value: route.action))
        items.append(contentsOf: route.queryItems)
        items.append(contentsOf: queryItems)
        components.queryItems = items
        return components.url
    }

    private struct LegacyMobileRoute {
        let action: String
        let queryItems: [URLQueryItem]
    }

    private func legacyMobileRoute(path: String, method: String) -> LegacyMobileRoute? {
        let normalizedMethod = method.uppercased()
        let segments = path
            .trimmingCharacters(in: CharacterSet(charactersIn: "/"))
            .split(separator: "/")
            .map(String.init)
        let first = segments.first ?? ""
        let second = segments.count > 1 ? segments[1] : ""
        let third = segments.count > 2 ? segments[2] : ""

        func route(_ action: String, _ queryItems: [URLQueryItem] = []) -> LegacyMobileRoute {
            LegacyMobileRoute(action: action, queryItems: queryItems)
        }

        func ticketQuery(_ value: String) -> [URLQueryItem] {
            if Int(value) != nil {
                return [
                    URLQueryItem(name: "id", value: value),
                    URLQueryItem(name: "ticket_id", value: value)
                ]
            }
            return [
                URLQueryItem(name: "ticket_hash", value: value),
                URLQueryItem(name: "hash", value: value)
            ]
        }

        if first == "login", normalizedMethod == "POST" { return route("mobile-login") }
        if first == "verify-2fa", normalizedMethod == "POST" { return route("mobile-verify-2fa") }
        if first == "refresh", normalizedMethod == "POST" { return route("mobile-refresh") }
        if first == "me", normalizedMethod == "GET" { return route("mobile-me") }
        if first == "logout", normalizedMethod == "POST" { return route("mobile-logout") }
        if ["shell", "app-shell"].contains(first), normalizedMethod == "GET" { return route("app-shell") }
        if ["home", "work"].contains(first), normalizedMethod == "GET" { return route("app-home") }
        if first == "tenant-state", normalizedMethod == "GET" { return route("app-tenant-state") }

        if first == "tickets" {
            if second.isEmpty {
                if normalizedMethod == "GET" { return route("app-ticket-list") }
                if normalizedMethod == "POST" { return route("app-create-ticket") }
            }
            if second == "create-options", normalizedMethod == "GET" {
                return route("app-ticket-create-options")
            }

            let ticketItems = ticketQuery(second)
            if third.isEmpty {
                if normalizedMethod == "GET" { return route("app-ticket-detail", ticketItems) }
                if normalizedMethod == "POST" { return route("app-update-ticket", ticketItems) }
            }
            if third == "actions", normalizedMethod == "GET" { return route("app-ticket-actions", ticketItems) }
            if third == "comments", normalizedMethod == "POST" { return route("app-add-comment", ticketItems) }
            if third == "comment-with-time", normalizedMethod == "POST" { return route("app-add-comment-with-time", ticketItems) }
            if third == "timer", normalizedMethod == "GET" { return route("app-ticket-timer", ticketItems) }
            if third == "timer", normalizedMethod == "POST" { return route("app-ticket-timer-action", ticketItems) }
            if third == "attachments", normalizedMethod == "POST" { return route("upload", ticketItems) }
        }

        if first == "attachments" {
            if second.isEmpty, normalizedMethod == "POST" { return route("upload") }
            if !second.isEmpty, normalizedMethod == "GET" {
                return route("app-attachment-metadata", [URLQueryItem(name: "attachment_id", value: second)])
            }
        }

        if first == "clients", !second.isEmpty, normalizedMethod == "GET" {
            return route("app-client-overview", [URLQueryItem(name: "organization_id", value: second)])
        }
        if first == "search", normalizedMethod == "GET" { return route("global-search") }
        if first == "device-token", second.isEmpty, normalizedMethod == "POST" { return route("mobile-register-device") }
        if first == "device-token", ["delete", "unregister"].contains(second), normalizedMethod == "POST" {
            return route("mobile-unregister-device")
        }
        if first == "notifications", second.isEmpty, normalizedMethod == "GET" { return route("app-notifications") }
        if first == "notifications", second == "summary", normalizedMethod == "GET" { return route("app-notifications-summary") }
        if first == "notifications", second == "read-state", normalizedMethod == "POST" { return route("app-notification-read-state") }
        if first == "reporting-review", normalizedMethod == "GET" { return route("app-reporting-review") }

        return nil
    }

    public func login(email: String, password: String, device: DeviceContext) async throws -> MobileAuthResponse {
        let request = MobileLoginRequest(
            email: email,
            password: password,
            deviceId: device.deviceId,
            deviceName: device.deviceName,
            appVersion: device.appVersion
        )
        return try await send(path: "login", method: "POST", body: request)
    }

    public func verifyTwoFactor(
        challengeToken: String,
        code: String,
        device: DeviceContext
    ) async throws -> MobileAuthResponse {
        let request = MobileTwoFactorRequest(
            challengeToken: challengeToken,
            code: code,
            deviceId: device.deviceId,
            deviceName: device.deviceName,
            appVersion: device.appVersion
        )
        return try await send(path: "verify-2fa", method: "POST", body: request)
    }

    public func refresh(refreshToken: String, device: DeviceContext) async throws -> MobileAuthResponse {
        let request = MobileRefreshRequest(
            refreshToken: refreshToken,
            deviceId: device.deviceId,
            deviceName: device.deviceName,
            appVersion: device.appVersion
        )
        return try await send(path: "refresh", method: "POST", body: request)
    }

    public func me(accessToken: String) async throws -> MobileMeResponse {
        try await send(path: "me", bearerToken: accessToken)
    }

    public func home(accessToken: String, limit: Int = 5) async throws -> AppEnvelope<HomePayload> {
        try await send(
            path: "work",
            queryItems: [URLQueryItem(name: "limit", value: String(limit))],
            bearerToken: accessToken
        )
    }

    public func tenantState(accessToken: String) async throws -> AppEnvelope<TenantStatePayload> {
        try await send(path: "tenant-state", bearerToken: accessToken)
    }

    public func ticketList(
        accessToken: String,
        view: String = "open",
        search: String = "",
        assignedTo: String? = nil,
        limit: Int = 25,
        offset: Int = 0
    ) async throws -> AppEnvelope<TicketListPayload> {
        var queryItems = [
            URLQueryItem(name: "view", value: view),
            URLQueryItem(name: "limit", value: String(limit)),
            URLQueryItem(name: "offset", value: String(offset))
        ]
        if !search.trimmingCharacters(in: .whitespacesAndNewlines).isEmpty {
            queryItems.append(URLQueryItem(name: "search", value: search))
        }
        if let assignedTo, !assignedTo.trimmingCharacters(in: .whitespacesAndNewlines).isEmpty {
            queryItems.append(URLQueryItem(name: "assigned_to", value: assignedTo))
        }

        return try await send(
            path: "tickets",
            queryItems: queryItems,
            bearerToken: accessToken
        )
    }

    public func ticketDetail(accessToken: String, ticketId: Int) async throws -> AppEnvelope<TicketDetailPayload> {
        try await send(
            path: "tickets/\(ticketId)",
            bearerToken: accessToken
        )
    }

    public func ticketActions(accessToken: String, ticketId: Int) async throws -> AppEnvelope<TicketActionsResponse> {
        try await send(
            path: "tickets/\(ticketId)/actions",
            bearerToken: accessToken
        )
    }

    public func clientOverview(
        accessToken: String,
        organizationId: Int,
        view: String = "open"
    ) async throws -> AppEnvelope<ClientOverviewPayload> {
        try await send(
            path: "clients/\(organizationId)",
            queryItems: [
                URLQueryItem(name: "view", value: view)
            ],
            bearerToken: accessToken
        )
    }

    public func createTicket(
        accessToken: String,
        request: CreateTicketRequest
    ) async throws -> AppEnvelope<CreateTicketResponse> {
        try await send(
            path: "tickets",
            method: "POST",
            bearerToken: accessToken,
            body: request
        )
    }

    public func createTicketOptions(accessToken: String) async throws -> AppEnvelope<CreateTicketOptionsPayload> {
        try await send(
            path: "tickets/create-options",
            bearerToken: accessToken
        )
    }

    public func updateTicket(
        accessToken: String,
        request: UpdateTicketRequest
    ) async throws -> AppEnvelope<UpdateTicketResponse> {
        try await send(
            path: "tickets/\(request.ticketId)",
            method: "POST",
            bearerToken: accessToken,
            body: request
        )
    }

    public func addComment(
        accessToken: String,
        request: AddCommentRequest
    ) async throws -> AppEnvelope<AddCommentResponse> {
        let path = (request.durationMinutes ?? 0) > 0
            ? "tickets/\(request.ticketId)/comment-with-time"
            : "tickets/\(request.ticketId)/comments"
        return try await send(
            path: path,
            method: "POST",
            bearerToken: accessToken,
            body: request
        )
    }

    public func attachmentMetadata(
        accessToken: String,
        attachmentId: Int
    ) async throws -> AppEnvelope<AttachmentMetadataPayload> {
        try await send(
            path: "attachments/\(attachmentId)",
            bearerToken: accessToken
        )
    }

    public func uploadAttachment(
        accessToken: String,
        ticketId: Int,
        filename: String,
        mimeType: String,
        data: Data
    ) async throws -> UploadResponse {
        try await sendMultipart(
            path: "tickets/\(ticketId)/attachments",
            bearerToken: accessToken,
            fields: [:],
            fileFieldName: "file",
            filename: filename,
            mimeType: mimeType,
            data: data
        )
    }

    public func ticketTimer(
        accessToken: String,
        ticketId: Int
    ) async throws -> AppEnvelope<TicketTimerPayload> {
        try await send(
            path: "tickets/\(ticketId)/timer",
            bearerToken: accessToken
        )
    }

    public func ticketTimerAction(
        accessToken: String,
        ticketId: Int,
        action: String
    ) async throws -> AppEnvelope<TimerActionPayload> {
        try await send(
            path: "tickets/\(ticketId)/timer",
            method: "POST",
            bearerToken: accessToken,
            body: TimerActionRequest(ticketId: ticketId, action: action)
        )
    }

    public func globalSearch(
        accessToken: String,
        query: String,
        limit: Int = 8
    ) async throws -> GlobalSearchResponse {
        try await send(
            path: "search",
            queryItems: [
                URLQueryItem(name: "q", value: query),
                URLQueryItem(name: "limit", value: String(limit))
            ],
            bearerToken: accessToken
        )
    }

    public func notifications(
        accessToken: String,
        limit: Int = 25,
        offset: Int = 0,
        includeResolved: Bool = false
    ) async throws -> AppEnvelope<NotificationsPayload> {
        try await send(
            path: "notifications",
            queryItems: [
                URLQueryItem(name: "limit", value: String(limit)),
                URLQueryItem(name: "offset", value: String(offset)),
                URLQueryItem(name: "include_resolved", value: includeResolved ? "1" : "0")
            ],
            bearerToken: accessToken
        )
    }

    public func setNotificationReadState(
        accessToken: String,
        request: NotificationReadStateRequest
    ) async throws -> AppEnvelope<NotificationReadStatePayload> {
        try await send(
            path: "notifications/read-state",
            method: "POST",
            bearerToken: accessToken,
            body: request
        )
    }

    public func resourceURL(from value: String?) -> URL? {
        guard let value = value, !value.trimmingCharacters(in: .whitespacesAndNewlines).isEmpty else {
            return nil
        }
        if let absolute = URL(string: value), absolute.scheme != nil {
            return absolute
        }
        return URL(string: value, relativeTo: environment.baseURL)?.absoluteURL
    }

    public func downloadResource(accessToken: String, url: URL) async throws -> Data {
        var request = URLRequest(url: url)
        request.httpMethod = "GET"
        request.setValue("*/*", forHTTPHeaderField: "Accept")

        if url.host == environment.baseURL.host {
            request.setValue("Bearer \(accessToken)", forHTTPHeaderField: "Authorization")
        }

        let (data, response) = try await session.data(for: request)
        guard let httpResponse = response as? HTTPURLResponse else {
            throw FoxDeskAPIError.invalidResponse
        }

        guard (200..<300).contains(httpResponse.statusCode) else {
            if httpResponse.statusCode == 401 {
                throw FoxDeskAPIError.unauthorized
            }
            throw FoxDeskAPIError.server(statusCode: httpResponse.statusCode, message: "FoxDesk download failed.")
        }

        return data
    }

    public func registerDevice(
        accessToken: String,
        apnsToken: String,
        environment: APNsEnvironment,
        device: DeviceContext
    ) async throws -> DeviceRegistrationResponse {
        let request = DeviceRegistrationRequest(
            apnsDeviceToken: apnsToken,
            apnsEnvironment: environment.rawValue,
            deviceId: device.deviceId,
            deviceName: device.deviceName,
            appVersion: device.appVersion
        )
        return try await send(
            path: "device-token",
            method: "POST",
            bearerToken: accessToken,
            body: request
        )
    }

    public func unregisterDevice(
        accessToken: String,
        device: DeviceContext
    ) async throws -> DeviceUnregisterResponse {
        try await send(
            path: "device-token/unregister",
            method: "POST",
            bearerToken: accessToken,
            body: DeviceUnregisterRequest(deviceId: device.deviceId)
        )
    }

    public func logout(refreshToken: String?, accessToken: String) async throws -> LogoutResponse {
        try await send(
            path: "logout",
            method: "POST",
            bearerToken: accessToken,
            body: LogoutRequest(refreshToken: refreshToken)
        )
    }

    private func send<Response: Decodable>(
        path: String,
        method: String = "GET",
        queryItems: [URLQueryItem] = [],
        bearerToken: String? = nil
    ) async throws -> Response {
        try await send(path: path, method: method, queryItems: queryItems, bearerToken: bearerToken, bodyData: nil)
    }

    private func send<Response: Decodable, Body: Encodable>(
        path: String,
        method: String = "GET",
        queryItems: [URLQueryItem] = [],
        bearerToken: String? = nil,
        body: Body
    ) async throws -> Response {
        let bodyData = try encoder.encode(body)
        return try await send(
            path: path,
            method: method,
            queryItems: queryItems,
            bearerToken: bearerToken,
            bodyData: bodyData
        )
    }

    private func send<Response: Decodable>(
        path: String,
        method: String,
        queryItems: [URLQueryItem],
        bearerToken: String?,
        bodyData: Data?
    ) async throws -> Response {
        var request = URLRequest(url: try apiURL(path: path, queryItems: queryItems))
        request.httpMethod = method
        request.setValue("application/json", forHTTPHeaderField: "Accept")
        if let bearerToken {
            request.setValue("Bearer \(bearerToken)", forHTTPHeaderField: "Authorization")
        }
        if let bodyData {
            request.httpBody = bodyData
            request.setValue("application/json", forHTTPHeaderField: "Content-Type")
        }

        let preferServerMessageForUnauthorized = ["login", "verify-2fa"].contains(
            path.trimmingCharacters(in: CharacterSet(charactersIn: "/"))
        )

        do {
            return try await sendJSONRequest(
                request,
                preferServerMessageForUnauthorized: preferServerMessageForUnauthorized
            )
        } catch FoxDeskAPIError.server(let statusCode, _) where statusCode == 404 {
            guard let fallbackURL = try legacyAPIURL(path: path, method: method, queryItems: queryItems) else {
                throw FoxDeskAPIError.server(statusCode: statusCode, message: "FoxDesk request failed.")
            }
            request.url = fallbackURL
            return try await sendJSONRequest(
                request,
                preferServerMessageForUnauthorized: preferServerMessageForUnauthorized
            )
        }
    }

    private func sendJSONRequest<Response: Decodable>(
        _ request: URLRequest,
        preferServerMessageForUnauthorized: Bool = false
    ) async throws -> Response {
        let (data, response) = try await session.data(for: request)
        guard let httpResponse = response as? HTTPURLResponse else {
            throw FoxDeskAPIError.invalidResponse
        }

        guard (200..<300).contains(httpResponse.statusCode) else {
            if httpResponse.statusCode == 401 {
                if preferServerMessageForUnauthorized,
                   let message = apiErrorMessage(from: data),
                   !message.isEmpty {
                    throw FoxDeskAPIError.server(statusCode: httpResponse.statusCode, message: message)
                }
                throw FoxDeskAPIError.unauthorized
            }
            let message = apiErrorMessage(from: data) ?? "FoxDesk request failed."
            throw FoxDeskAPIError.server(statusCode: httpResponse.statusCode, message: message)
        }

        do {
            return try decoder.decode(Response.self, from: data)
        } catch {
            throw FoxDeskAPIError.decoding(error.localizedDescription)
        }
    }

    private func apiErrorMessage(from data: Data) -> String? {
        guard let response = try? decoder.decode(APIErrorResponse.self, from: data) else {
            return nil
        }
        return response.message?.isEmpty == false ? response.message : response.error
    }

    private func sendMultipart<Response: Decodable>(
        path: String,
        bearerToken: String?,
        fields: [String: String],
        fileFieldName: String,
        filename: String,
        mimeType: String,
        data: Data
    ) async throws -> Response {
        let boundary = "FoxDeskBoundary-\(UUID().uuidString)"
        var request = URLRequest(url: try apiURL(path: path))
        request.httpMethod = "POST"
        request.setValue("application/json", forHTTPHeaderField: "Accept")
        request.setValue("multipart/form-data; boundary=\(boundary)", forHTTPHeaderField: "Content-Type")
        if let bearerToken {
            request.setValue("Bearer \(bearerToken)", forHTTPHeaderField: "Authorization")
        }

        var body = Data()
        for (name, value) in fields {
            body.appendMultipartBoundary(boundary)
            body.appendUTF8("Content-Disposition: form-data; name=\"\(name)\"\r\n\r\n")
            body.appendUTF8(value)
            body.appendUTF8("\r\n")
        }

        body.appendMultipartBoundary(boundary)
        body.appendUTF8("Content-Disposition: form-data; name=\"\(fileFieldName)\"; filename=\"\(filename)\"\r\n")
        body.appendUTF8("Content-Type: \(mimeType)\r\n\r\n")
        body.append(data)
        body.appendUTF8("\r\n")
        body.appendUTF8("--\(boundary)--\r\n")
        request.httpBody = body

        do {
            return try await sendMultipartRequest(request)
        } catch FoxDeskAPIError.server(let statusCode, _) where statusCode == 404 {
            guard let fallbackURL = try legacyAPIURL(path: path, method: "POST") else {
                throw FoxDeskAPIError.server(statusCode: statusCode, message: "FoxDesk upload failed.")
            }
            request.url = fallbackURL
            return try await sendMultipartRequest(request)
        }
    }

    private func sendMultipartRequest<Response: Decodable>(_ request: URLRequest) async throws -> Response {
        let (responseData, response) = try await session.data(for: request)
        guard let httpResponse = response as? HTTPURLResponse else {
            throw FoxDeskAPIError.invalidResponse
        }

        guard (200..<300).contains(httpResponse.statusCode) else {
            if httpResponse.statusCode == 401 {
                throw FoxDeskAPIError.unauthorized
            }
            let message = apiErrorMessage(from: responseData) ?? "FoxDesk upload failed."
            throw FoxDeskAPIError.server(statusCode: httpResponse.statusCode, message: message)
        }

        do {
            return try decoder.decode(Response.self, from: responseData)
        } catch {
            throw FoxDeskAPIError.decoding(error.localizedDescription)
        }
    }
}

private extension Data {
    mutating func appendUTF8(_ string: String) {
        append(Data(string.utf8))
    }

    mutating func appendMultipartBoundary(_ boundary: String) {
        appendUTF8("--\(boundary)\r\n")
    }
}
