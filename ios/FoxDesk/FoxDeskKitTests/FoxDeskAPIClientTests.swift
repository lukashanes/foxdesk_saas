import XCTest
@testable import FoxDeskKit

final class FoxDeskAPIClientTests: XCTestCase {
    override func tearDown() {
        URLProtocolStub.requestHandler = nil
        super.tearDown()
    }

    func testAPIURLBuildsVersionedMobilePath() throws {
        let client = FoxDeskAPIClient(environment: FoxDeskEnvironment(baseURL: URL(string: "https://app.foxdesk.net/index.php")!))

        let url = try client.apiURL(
            path: "tickets",
            queryItems: [URLQueryItem(name: "view", value: "open")]
        )

        let components = URLComponents(url: url, resolvingAgainstBaseURL: false)
        XCTAssertEqual(components?.scheme, "https")
        XCTAssertEqual(components?.host, "app.foxdesk.net")
        XCTAssertEqual(components?.path, "/api/mobile/v1/tickets")
        XCTAssertFalse(url.absoluteString.contains("page=api"))
        XCTAssertFalse(url.absoluteString.contains("action="))
        XCTAssertTrue(url.absoluteString.contains("view=open"))
    }

    func testLoginDecodesMobileSession() async throws {
        let client = FoxDeskAPIClient(
            environment: FoxDeskEnvironment(baseURL: URL(string: "https://app.foxdesk.net/index.php")!),
            session: makeStubbedSession()
        )
        URLProtocolStub.requestHandler = { request in
            XCTAssertEqual(request.httpMethod, "POST")
            Self.assertAPIPath(request.url, "/api/mobile/v1/login")

            let response = HTTPURLResponse(
                url: request.url!,
                statusCode: 200,
                httpVersion: nil,
                headerFields: ["Content-Type": "application/json"]
            )!
            let data = """
            {
              "success": true,
              "requires_2fa": false,
              "session": {
                "token_type": "Bearer",
                "access_token": "fdm_at_test",
                "refresh_token": "fdm_rt_test",
                "expires_in": 3600,
                "refresh_expires_in": 5184000
              },
              "user": {
                "id": 7,
                "email": "agent@example.com",
                "first_name": "Emma",
                "last_name": "Carter",
                "name": "Emma Carter",
                "role": "agent",
                "language": "en",
                "tenant_id": 3,
                "avatar": "uploads/avatar-emma.jpg"
              }
            }
            """.data(using: .utf8)!
            return (response, data)
        }

        let result = try await client.login(
            email: "agent@example.com",
            password: "secret",
            device: DeviceContext(deviceId: "device", deviceName: "iPhone", appVersion: "0.1")
        )

        XCTAssertEqual(result.session?.accessToken, "fdm_at_test")
        XCTAssertEqual(result.user?.name, "Emma Carter")
        XCTAssertEqual(result.user?.role, "agent")
        XCTAssertEqual(result.user?.avatar, "uploads/avatar-emma.jpg")
    }

    func testBearerTokenIsSentForMeRequest() async throws {
        let client = FoxDeskAPIClient(
            environment: FoxDeskEnvironment(baseURL: URL(string: "https://app.foxdesk.net/index.php")!),
            session: makeStubbedSession()
        )
        URLProtocolStub.requestHandler = { request in
            XCTAssertEqual(request.value(forHTTPHeaderField: "Authorization"), "Bearer fdm_at_test")
            Self.assertAPIPath(request.url, "/api/mobile/v1/me")

            let response = HTTPURLResponse(
                url: request.url!,
                statusCode: 200,
                httpVersion: nil,
                headerFields: ["Content-Type": "application/json"]
            )!
            let data = """
            {
              "success": true,
              "user": {
                "id": 7,
                "email": "agent@example.com",
                "first_name": "Emma",
                "last_name": "Carter",
                "name": "Emma Carter",
                "role": "agent",
                "language": "en",
                "tenant_id": 3,
                "avatar": "uploads/avatar-emma.jpg"
              }
            }
            """.data(using: .utf8)!
            return (response, data)
        }

        let result = try await client.me(accessToken: "fdm_at_test")

        XCTAssertEqual(result.user.email, "agent@example.com")
        XCTAssertEqual(result.user.avatar, "uploads/avatar-emma.jpg")
    }

    @MainActor
    func testAppSessionRestoreRefreshesExpiredAccessToken() async throws {
        let storedTokens = MobileSessionTokens(
            tokenType: "Bearer",
            accessToken: "stale_access",
            refreshToken: "stale_refresh",
            expiresIn: 0,
            refreshExpiresIn: 5_184_000
        )
        let tokenStore = InMemoryTokenStore(tokens: storedTokens)
        let client = FoxDeskAPIClient(
            environment: FoxDeskEnvironment(baseURL: URL(string: "https://app.foxdesk.net/index.php")!),
            session: makeStubbedSession()
        )
        let appSession = AppSession(
            client: client,
            tokenStore: tokenStore,
            device: DeviceContext(deviceId: "device", deviceName: "iPhone", appVersion: "0.1")
        )

        var requestIndex = 0
        URLProtocolStub.requestHandler = { request in
            defer { requestIndex += 1 }

            switch requestIndex {
            case 0:
                XCTAssertEqual(request.value(forHTTPHeaderField: "Authorization"), "Bearer stale_access")
                Self.assertAPIPath(request.url, "/api/mobile/v1/me")
                let response = HTTPURLResponse(
                    url: request.url!,
                    statusCode: 401,
                    httpVersion: nil,
                    headerFields: ["Content-Type": "application/json"]
                )!
                return (response, Data(#"{"success":false,"message":"Expired"}"#.utf8))

            case 1:
                XCTAssertNil(request.value(forHTTPHeaderField: "Authorization"))
                Self.assertAPIPath(request.url, "/api/mobile/v1/refresh")
                let body = String(data: Self.bodyData(from: request) ?? Data(), encoding: .utf8)
                XCTAssertEqual(body?.contains(#""refresh_token":"stale_refresh""#), true)
                let response = HTTPURLResponse(
                    url: request.url!,
                    statusCode: 200,
                    httpVersion: nil,
                    headerFields: ["Content-Type": "application/json"]
                )!
                let data = """
                {
                  "success": true,
                  "requires_2fa": false,
                  "session": {
                    "token_type": "Bearer",
                    "access_token": "fresh_access",
                    "refresh_token": "fresh_refresh",
                    "expires_in": 3600,
                    "refresh_expires_in": 5184000
                  },
                  "user": {
                    "id": 7,
                    "email": "agent@example.com",
                    "first_name": "Emma",
                    "last_name": "Carter",
                    "name": "Emma Carter",
                    "role": "agent",
                    "language": "en",
                    "tenant_id": 3
                  }
                }
                """.data(using: .utf8)!
                return (response, data)

            case 2:
                XCTAssertEqual(request.value(forHTTPHeaderField: "Authorization"), "Bearer fresh_access")
                Self.assertAPIPath(request.url, "/api/mobile/v1/me")
                let response = HTTPURLResponse(
                    url: request.url!,
                    statusCode: 200,
                    httpVersion: nil,
                    headerFields: ["Content-Type": "application/json"]
                )!
                let data = """
                {
                  "success": true,
                  "user": {
                    "id": 7,
                    "email": "agent@example.com",
                    "first_name": "Emma",
                    "last_name": "Carter",
                    "name": "Emma Carter",
                    "role": "agent",
                    "language": "en",
                    "tenant_id": 3
                  }
                }
                """.data(using: .utf8)!
                return (response, data)

            case 3:
                XCTAssertEqual(request.value(forHTTPHeaderField: "Authorization"), "Bearer fresh_access")
                Self.assertAPIPath(request.url, "/api/mobile/v1/tenant-state")
                let response = HTTPURLResponse(
                    url: request.url!,
                    statusCode: 200,
                    httpVersion: nil,
                    headerFields: ["Content-Type": "application/json"]
                )!
                let data = """
                {
                  "success": true,
                  "data": {
                    "tenant": {"id": 3, "name": "Aenze"},
                    "access": {"allowed": true, "state": "active", "reason": null, "message": null},
                    "billing_actions": null,
                    "usage": null,
                    "capabilities": {"manage_billing": false, "platform_admin": false},
                    "links": null
                  },
                  "meta": {"schema_version": 1, "resource": "tenant_state"},
                  "errors": []
                }
                """.data(using: .utf8)!
                return (response, data)

            default:
                XCTFail("Unexpected request: \(request.url?.absoluteString ?? "<missing url>")")
                throw URLError(.badServerResponse)
            }
        }

        await appSession.restore()

        XCTAssertEqual(appSession.state, .signedIn)
        XCTAssertEqual(appSession.user?.email, "agent@example.com")
        XCTAssertEqual(appSession.tokens?.accessToken, "fresh_access")
        let persistedTokens = try await tokenStore.loadTokens()
        XCTAssertEqual(persistedTokens?.accessToken, "fresh_access")
        XCTAssertEqual(persistedTokens?.refreshToken, "fresh_refresh")
        XCTAssertEqual(appSession.tenantState?.tenant.name, "Aenze")
        XCTAssertEqual(requestIndex, 4)
    }

    @MainActor
    func testAppSessionSignOutUnregistersDeviceBeforeLogout() async throws {
        let tokenStore = InMemoryTokenStore()
        let client = FoxDeskAPIClient(
            environment: FoxDeskEnvironment(baseURL: URL(string: "https://app.foxdesk.net/index.php")!),
            session: makeStubbedSession()
        )
        let appSession = AppSession(
            client: client,
            tokenStore: tokenStore,
            device: DeviceContext(deviceId: "device", deviceName: "iPhone", appVersion: "0.1")
        )

        var requestIndex = 0
        URLProtocolStub.requestHandler = { request in
            defer { requestIndex += 1 }

            switch requestIndex {
            case 0:
                XCTAssertNil(request.value(forHTTPHeaderField: "Authorization"))
                Self.assertAPIPath(request.url, "/api/mobile/v1/login")
                let response = HTTPURLResponse(
                    url: request.url!,
                    statusCode: 200,
                    httpVersion: nil,
                    headerFields: ["Content-Type": "application/json"]
                )!
                let data = """
                {
                  "success": true,
                  "requires_2fa": false,
                  "session": {
                    "token_type": "Bearer",
                    "access_token": "fresh_access",
                    "refresh_token": "fresh_refresh",
                    "expires_in": 3600,
                    "refresh_expires_in": 5184000
                  },
                  "user": {
                    "id": 7,
                    "email": "agent@example.com",
                    "first_name": "Emma",
                    "last_name": "Carter",
                    "name": "Emma Carter",
                    "role": "agent",
                    "language": "en",
                    "tenant_id": 3
                  }
                }
                """.data(using: .utf8)!
                return (response, data)

            case 1:
                XCTAssertEqual(request.value(forHTTPHeaderField: "Authorization"), "Bearer fresh_access")
                Self.assertAPIPath(request.url, "/api/mobile/v1/tenant-state")
                let response = HTTPURLResponse(
                    url: request.url!,
                    statusCode: 200,
                    httpVersion: nil,
                    headerFields: ["Content-Type": "application/json"]
                )!
                let data = """
                {
                  "success": true,
                  "data": {
                    "tenant": {"id": 3, "name": "Aenze"},
                    "access": {"allowed": true, "state": "active", "reason": null, "message": null},
                    "billing_actions": null,
                    "usage": null,
                    "capabilities": {"manage_billing": false, "platform_admin": false},
                    "links": null
                  },
                  "meta": {"schema_version": 1, "resource": "tenant_state"},
                  "errors": []
                }
                """.data(using: .utf8)!
                return (response, data)

            case 2:
                XCTAssertEqual(request.value(forHTTPHeaderField: "Authorization"), "Bearer fresh_access")
                Self.assertAPIPath(request.url, "/api/mobile/v1/device-token/unregister")
                let body = try XCTUnwrap(Self.bodyData(from: request))
                let json = try XCTUnwrap(JSONSerialization.jsonObject(with: body) as? [String: Any])
                XCTAssertEqual(json["device_id"] as? String, "device")
                let response = HTTPURLResponse(
                    url: request.url!,
                    statusCode: 200,
                    httpVersion: nil,
                    headerFields: ["Content-Type": "application/json"]
                )!
                return (response, Data(#"{"success":true,"unregistered":true}"#.utf8))

            case 3:
                XCTAssertEqual(request.value(forHTTPHeaderField: "Authorization"), "Bearer fresh_access")
                Self.assertAPIPath(request.url, "/api/mobile/v1/logout")
                let body = try XCTUnwrap(Self.bodyData(from: request))
                let json = try XCTUnwrap(JSONSerialization.jsonObject(with: body) as? [String: Any])
                XCTAssertEqual(json["refresh_token"] as? String, "fresh_refresh")
                let response = HTTPURLResponse(
                    url: request.url!,
                    statusCode: 200,
                    httpVersion: nil,
                    headerFields: ["Content-Type": "application/json"]
                )!
                return (response, Data(#"{"success":true,"logged_out":true}"#.utf8))

            default:
                XCTFail("Unexpected request: \(request.url?.absoluteString ?? "<missing url>")")
                throw URLError(.badServerResponse)
            }
        }

        await appSession.signIn(email: "agent@example.com", password: "secret")
        XCTAssertEqual(appSession.state, .signedIn)

        await appSession.signOut()

        XCTAssertEqual(appSession.state, .signedOut)
        XCTAssertNil(appSession.tokens)
        let clearedTokens = try await tokenStore.loadTokens()
        XCTAssertNil(clearedTokens)
        XCTAssertEqual(requestIndex, 4)
    }

    @MainActor
    func testSignOutClearsLocalSessionEvenWhenServerLogoutFails() async throws {
        let storedTokens = MobileSessionTokens(
            tokenType: "Bearer",
            accessToken: "access_to_clear",
            refreshToken: "refresh_to_clear",
            expiresIn: 3600,
            refreshExpiresIn: 5_184_000
        )
        let tokenStore = InMemoryTokenStore(tokens: storedTokens)
        let client = FoxDeskAPIClient(
            environment: FoxDeskEnvironment(baseURL: URL(string: "https://app.foxdesk.net/index.php")!),
            session: makeStubbedSession()
        )
        let appSession = AppSession(
            client: client,
            tokenStore: tokenStore,
            device: DeviceContext(deviceId: "device", deviceName: "iPhone", appVersion: "0.1")
        )

        var requestIndex = 0
        var requestedPaths: [String] = []
        URLProtocolStub.requestHandler = { request in
            defer { requestIndex += 1 }
            requestedPaths.append(request.url?.path ?? "")

            switch requestIndex {
            case 0:
                Self.assertAPIPath(request.url, "/api/mobile/v1/login")
                let response = HTTPURLResponse(
                    url: request.url!,
                    statusCode: 200,
                    httpVersion: nil,
                    headerFields: ["Content-Type": "application/json"]
                )!
                let data = """
                {
                  "success": true,
                  "requires_2fa": false,
                  "session": {
                    "token_type": "Bearer",
                    "access_token": "access_to_clear",
                    "refresh_token": "refresh_to_clear",
                    "expires_in": 3600,
                    "refresh_expires_in": 5184000
                  },
                  "user": {
                    "id": 7,
                    "email": "agent@example.com",
                    "first_name": "Emma",
                    "last_name": "Carter",
                    "name": "Emma Carter",
                    "role": "agent",
                    "language": "en",
                    "tenant_id": 3
                  }
                }
                """.data(using: .utf8)!
                return (response, data)

            case 1:
                Self.assertAPIPath(request.url, "/api/mobile/v1/tenant-state")
                let response = HTTPURLResponse(
                    url: request.url!,
                    statusCode: 200,
                    httpVersion: nil,
                    headerFields: ["Content-Type": "application/json"]
                )!
                let data = """
                {
                  "success": true,
                  "data": {
                    "tenant": {"id": 3, "name": "Aenze"},
                    "access": {"allowed": true, "state": "active", "reason": null, "message": null},
                    "billing_actions": null,
                    "usage": null,
                    "capabilities": {"manage_billing": false, "platform_admin": false},
                    "links": null
                  },
                  "meta": {"schema_version": 1, "resource": "tenant_state"},
                  "errors": []
                }
                """.data(using: .utf8)!
                return (response, data)

            case 2:
                Self.assertAPIPath(request.url, "/api/mobile/v1/device-token/unregister")
                let response = HTTPURLResponse(
                    url: request.url!,
                    statusCode: 500,
                    httpVersion: nil,
                    headerFields: ["Content-Type": "application/json"]
                )!
                return (response, Data(#"{"success":false,"message":"temporary unregister failure"}"#.utf8))

            case 3:
                Self.assertAPIPath(request.url, "/api/mobile/v1/logout")
                let response = HTTPURLResponse(
                    url: request.url!,
                    statusCode: 500,
                    httpVersion: nil,
                    headerFields: ["Content-Type": "application/json"]
                )!
                return (response, Data(#"{"success":false,"message":"temporary logout failure"}"#.utf8))

            default:
                XCTFail("Unexpected request: \(request.url?.absoluteString ?? "<missing url>")")
                throw URLError(.badServerResponse)
            }
        }

        await appSession.signIn(email: "agent@example.com", password: "secret")
        XCTAssertEqual(appSession.state, .signedIn)
        await appSession.signOut()

        XCTAssertEqual(appSession.state, .signedOut)
        XCTAssertNil(appSession.tokens)
        XCTAssertNil(appSession.user)
        let clearedTokens = try await tokenStore.loadTokens()
        XCTAssertNil(clearedTokens)
        XCTAssertTrue(requestedPaths.contains("/api/mobile/v1/device-token/unregister"))
        XCTAssertTrue(requestedPaths.contains("/api/mobile/v1/logout"))
        XCTAssertEqual(requestIndex, 4)
    }

    func testTicketListSendsViewSearchAndDecodesRows() async throws {
        let client = FoxDeskAPIClient(
            environment: FoxDeskEnvironment(baseURL: URL(string: "https://app.foxdesk.net/index.php")!),
            session: makeStubbedSession()
        )
        URLProtocolStub.requestHandler = { request in
            XCTAssertEqual(request.value(forHTTPHeaderField: "Authorization"), "Bearer fdm_at_test")
            Self.assertAPIPath(request.url, "/api/mobile/v1/tickets")
            XCTAssertEqual(request.url?.query?.contains("view=waiting"), true)
            XCTAssertEqual(request.url?.query?.contains("search=vpn"), true)

            let response = HTTPURLResponse(
                url: request.url!,
                statusCode: 200,
                httpVersion: nil,
                headerFields: ["Content-Type": "application/json"]
            )!
            let data = """
            {
              "success": true,
              "data": {
                "tickets": [
                  {
                    "id": 42,
                    "hash": "abc",
                    "code": "TK-10042",
                    "title": "VPN access stopped working",
                    "description_preview": "VPN rejects MFA codes",
                    "status": {"id": 1, "name": "Waiting", "color": "#335CFF", "group": "waiting", "is_closed": false},
                    "client": {"id": 3, "name": "Aenze"},
                    "attachment_count": 2,
                    "is_archived": false
                  }
                ],
                "view": "waiting",
                "views": null,
                "counts": null,
                "pagination": {"limit": 25, "offset": 0, "total": 1, "has_more": false},
                "filters": null
              },
              "meta": {"schema_version": 1, "resource": "ticket_list"},
              "errors": []
            }
            """.data(using: .utf8)!
            return (response, data)
        }

        let result = try await client.ticketList(accessToken: "fdm_at_test", view: "waiting", search: "vpn")

        XCTAssertEqual(result.data.tickets.first?.title, "VPN access stopped working")
        XCTAssertEqual(result.data.tickets.first?.status?.group, "waiting")
        XCTAssertEqual(result.data.pagination?.total, 1)
    }

    func testTicketListSendsCustomLimitAndOffsetForPagination() async throws {
        let client = FoxDeskAPIClient(
            environment: FoxDeskEnvironment(baseURL: URL(string: "https://app.foxdesk.net/index.php")!),
            session: makeStubbedSession()
        )
        URLProtocolStub.requestHandler = { request in
            Self.assertAPIPath(request.url, "/api/mobile/v1/tickets")
            XCTAssertEqual(request.url?.query?.contains("view=all"), true)
            XCTAssertEqual(request.url?.query?.contains("limit=10"), true)
            XCTAssertEqual(request.url?.query?.contains("offset=25"), true)

            let response = HTTPURLResponse(
                url: request.url!,
                statusCode: 200,
                httpVersion: nil,
                headerFields: ["Content-Type": "application/json"]
            )!
            let data = """
            {
              "success": true,
              "data": {
                "tickets": [],
                "view": "all",
                "views": null,
                "counts": null,
                "pagination": {"limit": 10, "offset": 25, "total": 47, "has_more": true},
                "filters": null
              },
              "meta": {"schema_version": 1, "resource": "ticket_list"},
              "errors": []
            }
            """.data(using: .utf8)!
            return (response, data)
        }

        let result = try await client.ticketList(
            accessToken: "fdm_at_test",
            view: "all",
            limit: 10,
            offset: 25
        )

        XCTAssertEqual(result.data.pagination?.offset, 25)
        XCTAssertEqual(result.data.pagination?.hasMore, true)
    }

    func testTicketListCanRequestNewTicketsView() async throws {
        let client = FoxDeskAPIClient(
            environment: FoxDeskEnvironment(baseURL: URL(string: "https://app.foxdesk.net/index.php")!),
            session: makeStubbedSession()
        )
        URLProtocolStub.requestHandler = { request in
            XCTAssertEqual(request.httpMethod, "GET")
            XCTAssertEqual(request.value(forHTTPHeaderField: "Authorization"), "Bearer fdm_at_test")
            Self.assertAPIPath(request.url, "/api/mobile/v1/tickets")
            XCTAssertEqual(request.url?.query?.contains("view=new"), true)

            let response = HTTPURLResponse(
                url: request.url!,
                statusCode: 200,
                httpVersion: nil,
                headerFields: ["Content-Type": "application/json"]
            )!
            let data = """
            {
              "success": true,
              "data": {
                "tickets": [],
                "view": "new",
                "views": null,
                "counts": {"new": 3, "open": 8, "waiting": 1, "done": 4, "all": 12},
                "pagination": {"limit": 25, "offset": 0, "total": 3, "has_more": false},
                "filters": null
              },
              "meta": {"schema_version": 1, "resource": "ticket_list"},
              "errors": []
            }
            """.data(using: .utf8)!
            return (response, data)
        }

        let result = try await client.ticketList(accessToken: "fdm_at_test", view: "new")

        XCTAssertEqual(result.data.view, "new")
        XCTAssertNotNil(result.data.counts)
    }

    func testTicketListCanRequestCurrentUsersAssignedTickets() async throws {
        let client = FoxDeskAPIClient(
            environment: FoxDeskEnvironment(baseURL: URL(string: "https://app.foxdesk.net/index.php")!),
            session: makeStubbedSession()
        )
        URLProtocolStub.requestHandler = { request in
            XCTAssertEqual(request.httpMethod, "GET")
            XCTAssertEqual(request.value(forHTTPHeaderField: "Authorization"), "Bearer fdm_at_test")
            Self.assertAPIPath(request.url, "/api/mobile/v1/tickets")
            XCTAssertEqual(request.url?.query?.contains("view=open"), true)
            XCTAssertEqual(request.url?.query?.contains("assigned_to=me"), true)

            let response = HTTPURLResponse(
                url: request.url!,
                statusCode: 200,
                httpVersion: nil,
                headerFields: ["Content-Type": "application/json"]
            )!
            let data = """
            {
              "success": true,
              "data": {
                "tickets": [],
                "view": "open",
                "views": null,
                "counts": null,
                "pagination": {"limit": 25, "offset": 0, "total": 0, "has_more": false},
                "filters": {"search": "", "sort": "last_updated"}
              },
              "meta": {"schema_version": 1, "resource": "ticket_list"},
              "errors": []
            }
            """.data(using: .utf8)!
            return (response, data)
        }

        let result = try await client.ticketList(accessToken: "fdm_at_test", view: "open", assignedTo: "me")

        XCTAssertEqual(result.data.tickets.count, 0)
        XCTAssertEqual(result.data.view, "open")
    }

    func testHomeDecodesWorkQueuesTimersAndNotifications() async throws {
        let client = FoxDeskAPIClient(
            environment: FoxDeskEnvironment(baseURL: URL(string: "https://app.foxdesk.net/index.php")!),
            session: makeStubbedSession()
        )
        URLProtocolStub.requestHandler = { request in
            XCTAssertEqual(request.httpMethod, "GET")
            XCTAssertEqual(request.value(forHTTPHeaderField: "Authorization"), "Bearer fdm_at_test")
            Self.assertAPIPath(request.url, "/api/mobile/v1/work")
            XCTAssertEqual(request.url?.query?.contains("limit=5"), true)

            let response = HTTPURLResponse(
                url: request.url!,
                statusCode: 200,
                httpVersion: nil,
                headerFields: ["Content-Type": "application/json"]
            )!
            let data = """
            {
              "success": true,
              "data": {
                "home": {
                  "schema_version": 1,
                  "generated_at": "2026-07-05T09:35:00+00:00",
                  "limit": 5,
                  "work": {
                    "mine": {
                      "definition": {"key": "mine", "title": "My work", "description": "Assigned to me"},
                      "count": 1,
                      "items": [
                        {
                          "id": 42,
                          "hash": "abc",
                          "code": "TK-10042",
                          "title": "VPN access stopped working",
                          "description_preview": "VPN rejects MFA codes",
                          "status": {"name": "Waiting", "color": "#335CFF", "group": "waiting"},
                          "priority": {"name": "High", "color": "#FFAA00"},
                          "client": {"id": 3, "name": "Aenze"},
                          "requester": "Eva Novak",
                          "assignee": "Emma Carter",
                          "source": "email"
                        }
                      ]
                    }
                  },
                  "inbox": {},
                  "timers": [
                    {
                      "entry_id": 77,
                      "ticket_id": 42,
                      "ticket_title": "VPN access stopped working",
                      "is_paused": false,
                      "elapsed_minutes": 25,
                      "elapsed_label": "25 min"
                    }
                  ],
                  "time": {
                    "period": {"key": "last_30_days", "label": "Last 30 days", "start": "2026-06-06 00:00:00", "end": "2026-07-05 23:59:59"},
                    "totals": {
                      "today": {"minutes": 35, "label": "35 min"},
                      "week": {"minutes": 120, "label": "2h 0min"},
                      "month": {"minutes": 320, "label": "5h 20min"},
                      "selected": {"minutes": 420, "label": "7h 0min"}
                    },
                    "entries": [
                      {
                        "id": 800,
                        "ticket_id": 42,
                        "ticket_hash": "abc",
                        "ticket_code": "TK-10042",
                        "ticket_title": "VPN access stopped working",
                        "client_name": "Aenze",
                        "status_name": "Waiting",
                        "summary": "Checked VPN profile.",
                        "started_at": "2026-07-05 09:10:00",
                        "ended_at": "2026-07-05 09:35:00",
                        "minutes": 25,
                        "minutes_label": "25 min"
                      }
                    ],
                    "team": [
                      {
                        "user_id": 7,
                        "name": "Emma Carter",
                        "email": "emma@example.test",
                        "role": "agent",
                        "avatar": "uploads/avatar-emma.jpg",
                        "is_running": true,
                        "totals": {
                          "selected": {"minutes": 95, "label": "1h 35min"},
                          "month": {"minutes": 320, "label": "5h 20min"}
                        },
                        "entries": [
                          {
                            "id": 801,
                            "ticket_id": 42,
                            "ticket_hash": "abc",
                            "ticket_code": "TK-10042",
                            "ticket_title": "VPN access stopped working",
                            "client_name": "Aenze",
                            "status_name": "Waiting",
                            "summary": "Checked VPN profile.",
                            "started_at": "2026-07-05 09:10:00",
                            "ended_at": "2026-07-05 09:35:00",
                            "minutes": 25,
                            "minutes_label": "25 min"
                          }
                        ],
                        "latest_entry": {
                          "id": 801,
                          "ticket_id": 42,
                          "ticket_hash": "abc",
                          "ticket_code": "TK-10042",
                          "ticket_title": "VPN access stopped working",
                          "client_name": "Aenze",
                          "status_name": "Waiting",
                          "summary": "Checked VPN profile.",
                          "started_at": "2026-07-05 09:10:00",
                          "ended_at": "2026-07-05 09:35:00",
                          "minutes": 25,
                          "minutes_label": "25 min"
                        }
                      }
                    ],
                    "chart": {
                      "days": [
                        {"key": "2026-07-04", "label": "04.07.", "full_label": "Saturday, July 4", "minutes": 95, "minutes_label": "1h 35min", "users": []},
                        {"key": "2026-07-05", "label": "05.07.", "full_label": "Sunday, July 5", "minutes": 35, "minutes_label": "35 min", "users": [{"user_id": 7, "name": "Emma Carter", "minutes": 35, "minutes_label": "35 min"}]}
                      ],
                      "max_minutes": 95,
                      "total_minutes": 130,
                      "total_label": "2h 10min"
                    }
                  },
                  "notifications": {
                    "unread_count": 3,
                    "items": [
                      {
                        "id": 101,
                        "type": "new_comment",
                        "ticket_id": 42,
                        "is_read": false,
                        "is_resolved": false,
                        "created_at": "2026-07-05 09:40:00",
                        "time_ago": "2 min ago",
                        "text": "New reply on VPN access stopped working",
                        "action_text": "Open ticket",
                        "snippet": "The VPN client now asks for MFA.",
                        "is_action": true,
                        "actor": {"name": "Eva Novak", "email": "eva@example.test", "avatar": "uploads/eva.jpg"}
                      }
                    ]
                  }
                }
              },
              "meta": {"schema_version": 1, "resource": "home"},
              "errors": []
            }
            """.data(using: .utf8)!
            return (response, data)
        }

        let result = try await client.home(accessToken: "fdm_at_test")

        XCTAssertEqual(result.data.home.work?["mine"]?.definition?.title, "My work")
        XCTAssertEqual(result.data.home.work?["mine"]?.items?.first?.requester, "Eva Novak")
        XCTAssertEqual(result.data.home.timers?.first?.elapsedMinutes, 25)
        XCTAssertEqual(result.data.home.time?.totals?["today"]?.label, "35 min")
        XCTAssertEqual(result.data.home.time?.entries?.first?.ticketCode, "TK-10042")
        XCTAssertEqual(result.data.home.time?.team?.first?.name, "Emma Carter")
        XCTAssertEqual(result.data.home.time?.team?.first?.avatar, "uploads/avatar-emma.jpg")
        XCTAssertEqual(result.data.home.time?.team?.first?.totals?["selected"]?.label, "1h 35min")
        XCTAssertEqual(result.data.home.time?.team?.first?.latestEntry?.ticketId, 42)
        XCTAssertEqual(result.data.home.time?.chart?.days?.last?.users?.first?.name, "Emma Carter")
        XCTAssertEqual(result.data.home.notifications?.unreadCount, 3)
        XCTAssertEqual(result.data.home.notifications?.items?.first?.ticketId, 42)
        XCTAssertEqual(result.data.home.notifications?.items?.first?.actor?.name, "Eva Novak")
    }

    func testTenantStateSendsBearerAndDecodesAccessState() async throws {
        let client = FoxDeskAPIClient(
            environment: FoxDeskEnvironment(baseURL: URL(string: "https://app.foxdesk.net/index.php")!),
            session: makeStubbedSession()
        )
        URLProtocolStub.requestHandler = { request in
            XCTAssertEqual(request.httpMethod, "GET")
            XCTAssertEqual(request.value(forHTTPHeaderField: "Authorization"), "Bearer fdm_at_test")
            Self.assertAPIPath(request.url, "/api/mobile/v1/tenant-state")

            let response = HTTPURLResponse(
                url: request.url!,
                statusCode: 200,
                httpVersion: nil,
                headerFields: ["Content-Type": "application/json"]
            )!
            let data = """
            {
              "success": true,
              "data": {
                "tenant": {
                  "id": 3,
                  "name": "Aenze",
                  "slug": "aenze",
                  "status": "past_due",
                  "subscription_status": "past_due",
                  "billing_email": "billing@aenze.com",
                  "trial_ends_at": null,
                  "suspended_at": "2026-07-05 09:35:00"
                },
                "access": {
                  "allowed": false,
                  "reason": "suspended",
                  "state": "suspended",
                  "message": "Update payment to continue using this workspace."
                },
                "billing_actions": {
                  "show_checkout": false,
                  "checkout_label": "Start plan",
                  "show_portal": true,
                  "portal_label": "Update payment",
                  "notice_title": "We could not process payment",
                  "notice_body": "Update payment to continue.",
                  "notice_variant": "warning"
                },
                "usage": null,
                "capabilities": {"manage_billing": true, "platform_admin": false},
                "links": null
              },
              "meta": {"schema_version": 1, "resource": "tenant_state"},
              "errors": []
            }
            """.data(using: .utf8)!
            return (response, data)
        }

        let result = try await client.tenantState(accessToken: "fdm_at_test")

        XCTAssertEqual(result.data.tenant.name, "Aenze")
        XCTAssertEqual(result.data.access.allowed, false)
        XCTAssertEqual(result.data.access.message, "Update payment to continue using this workspace.")
        XCTAssertEqual(result.data.billingActions?.noticeTitle, "We could not process payment")
        XCTAssertEqual(result.data.capabilities?.manageBilling, true)
    }

    func testClientOverviewSendsOrganizationViewAndDecodesContext() async throws {
        let client = FoxDeskAPIClient(
            environment: FoxDeskEnvironment(baseURL: URL(string: "https://app.foxdesk.net/index.php")!),
            session: makeStubbedSession()
        )
        URLProtocolStub.requestHandler = { request in
            XCTAssertEqual(request.httpMethod, "GET")
            XCTAssertEqual(request.value(forHTTPHeaderField: "Authorization"), "Bearer fdm_at_test")
            Self.assertAPIPath(request.url, "/api/mobile/v1/clients/3")
            XCTAssertEqual(request.url?.query?.contains("view=open"), true)

            let response = HTTPURLResponse(
                url: request.url!,
                statusCode: 200,
                httpVersion: nil,
                headerFields: ["Content-Type": "application/json"]
            )!
            let data = """
            {
              "success": true,
              "data": {
                "client": {
                  "id": 3,
                  "name": "Aenze",
                  "email": "support@aenze.com",
                  "phone": "",
                  "is_active": true,
                  "billable_rate": 1000
                },
                "view": "open",
                "counts": {"open": 4, "waiting": 1, "done": 8, "archived": 2, "all": 14},
                "tickets": [
                  {"id": 42, "title": "VPN access stopped working", "code": "TK-10042"}
                ],
                "contacts": [
                  {"id": 5, "name": "Eva Novak", "email": "eva@aenze.com", "role": "Owner", "is_active": true}
                ],
                "time": {
                  "minutes": 120,
                  "billable_minutes": 120,
                  "billable_amount": 2000,
                  "minutes_label": "2h 0min",
                  "billable_amount_label": "2 000.00 CZK"
                },
                "links": null
              },
              "meta": {"schema_version": 1, "resource": "client_overview"},
              "errors": []
            }
            """.data(using: .utf8)!
            return (response, data)
        }

        let result = try await client.clientOverview(accessToken: "fdm_at_test", organizationId: 3)

        XCTAssertEqual(result.data.client.name, "Aenze")
        XCTAssertEqual(result.data.counts?.open, 4)
        XCTAssertEqual(result.data.contacts.first?.email, "eva@aenze.com")
        XCTAssertEqual(result.data.time?.minutesLabel, "2h 0min")
        XCTAssertEqual(result.data.tickets.first?.code, "TK-10042")
    }

    func testCreateTicketOptionsLoadsClientPriorityAndAssigneeChoices() async throws {
        let client = FoxDeskAPIClient(
            environment: FoxDeskEnvironment(baseURL: URL(string: "https://app.foxdesk.net/index.php")!),
            session: makeStubbedSession()
        )
        URLProtocolStub.requestHandler = { request in
            XCTAssertEqual(request.httpMethod, "GET")
            XCTAssertEqual(request.value(forHTTPHeaderField: "Authorization"), "Bearer fdm_at_test")
            Self.assertAPIPath(request.url, "/api/mobile/v1/tickets/create-options")

            let response = HTTPURLResponse(
                url: request.url!,
                statusCode: 200,
                httpVersion: nil,
                headerFields: ["Content-Type": "application/json"]
            )!
            let data = """
            {
              "success": true,
              "data": {
                "clients": [
                  {"id": 3, "name": "Aenze", "email": "support@aenze.com", "is_active": true}
                ],
                "statuses": [
                  {"id": 1, "name": "New", "color": "#335CFF", "group": "open", "is_closed": false}
                ],
                "priorities": [
                  {"id": 2, "name": "Medium", "color": "#3b82f6"}
                ],
                "assignees": [
                  {"id": 7, "name": "Emma Carter", "email": "emma@example.com", "role": "agent"}
                ],
                "defaults": {"status_id": 1, "priority_id": 2, "assignee_id": 7}
              },
              "meta": {"schema_version": 1, "resource": "ticket_create_options"},
              "errors": []
            }
            """.data(using: .utf8)!
            return (response, data)
        }

        let result = try await client.createTicketOptions(accessToken: "fdm_at_test")

        XCTAssertEqual(result.data.clients.first?.name, "Aenze")
        XCTAssertEqual(result.data.priorities.first?.id, 2)
        XCTAssertEqual(result.data.assignees.first?.name, "Emma Carter")
        XCTAssertEqual(result.data.defaults?.priorityId, 2)
    }

    func testCreateTicketUsesAppCreateTicketAndDecodesResponse() async throws {
        let client = FoxDeskAPIClient(
            environment: FoxDeskEnvironment(baseURL: URL(string: "https://app.foxdesk.net/index.php")!),
            session: makeStubbedSession()
        )
        URLProtocolStub.requestHandler = { request in
            XCTAssertEqual(request.httpMethod, "POST")
            XCTAssertEqual(request.value(forHTTPHeaderField: "Authorization"), "Bearer fdm_at_test")
            Self.assertAPIPath(request.url, "/api/mobile/v1/tickets")

            let body = try XCTUnwrap(Self.bodyData(from: request))
            let json = try XCTUnwrap(JSONSerialization.jsonObject(with: body) as? [String: Any])
            XCTAssertEqual(json["title"] as? String, "VPN access stopped working")
            XCTAssertEqual(json["description"] as? String, "<p>VPN rejects MFA codes.</p>")
            XCTAssertEqual(json["organization_id"] as? Int, 3)
            XCTAssertEqual(json["assignee_id"] as? Int, 7)
            XCTAssertEqual(json["priority_id"] as? Int, 2)
            XCTAssertEqual(json["status_id"] as? Int, 1)
            XCTAssertEqual(json["due_date"] as? String, "2026-07-08")
            XCTAssertEqual(json["tags"] as? String, "vpn,mfa")
            XCTAssertEqual(json["created_at"] as? String, "2026-07-05 09:10:00")

            let response = HTTPURLResponse(
                url: request.url!,
                statusCode: 200,
                httpVersion: nil,
                headerFields: ["Content-Type": "application/json"]
            )!
            let data = """
            {
              "success": true,
              "data": {
                "ticket_id": 42,
                "ticket_hash": "abc",
                "ticket_code": "TK-10042",
                "ticket": {
                  "id": 42,
                  "hash": "abc",
                  "code": "TK-10042",
                  "title": "VPN access stopped working"
                }
              },
              "meta": {"schema_version": 1, "resource": "ticket"},
              "errors": []
            }
            """.data(using: .utf8)!
            return (response, data)
        }

        let result = try await client.createTicket(
            accessToken: "fdm_at_test",
            request: CreateTicketRequest(
                title: "VPN access stopped working",
                description: "<p>VPN rejects MFA codes.</p>",
                organizationId: 3,
                assigneeId: 7,
                priorityId: 2,
                statusId: 1,
                dueDate: "2026-07-08",
                tags: "vpn,mfa",
                createdAt: "2026-07-05 09:10:00"
            )
        )

        XCTAssertEqual(result.data.ticketId, 42)
        XCTAssertEqual(result.data.ticketCode, "TK-10042")
        XCTAssertEqual(result.data.ticket?.title, "VPN access stopped working")
    }

    func testGlobalSearchDecodesGroupedResults() async throws {
        let client = FoxDeskAPIClient(
            environment: FoxDeskEnvironment(baseURL: URL(string: "https://app.foxdesk.net/index.php")!),
            session: makeStubbedSession()
        )
        URLProtocolStub.requestHandler = { request in
            XCTAssertEqual(request.httpMethod, "GET")
            XCTAssertEqual(request.value(forHTTPHeaderField: "Authorization"), "Bearer fdm_at_test")
            Self.assertAPIPath(request.url, "/api/mobile/v1/search")
            XCTAssertEqual(request.url?.query?.contains("q=vpn"), true)

            let response = HTTPURLResponse(
                url: request.url!,
                statusCode: 200,
                httpVersion: nil,
                headerFields: ["Content-Type": "application/json"]
            )!
            let data = """
            {
              "success": true,
              "query": "vpn",
              "sections": {
                "open_tickets": {
                  "definition": {"label": "Open tickets", "type": "tickets"},
                  "items": [
                    {
                      "type": "ticket",
                      "id": 42,
                      "title": "VPN access stopped working",
                      "code": "TK-10042",
                      "status": "Waiting",
                      "status_group": "waiting",
                      "client": "Aenze",
                      "assignee": "Emma Carter",
                      "updated_at": "2026-07-05 09:35:00"
                    }
                  ]
                },
                "clients": {
                  "definition": {"label": "Clients", "type": "clients"},
                  "items": [
                    {"type": "client", "id": 3, "title": "Aenze", "subtitle": "1 open ticket"}
                  ]
                },
                "contacts": {
                  "definition": {"label": "Contacts", "type": "contacts"},
                  "items": [
                    {
                      "type": "contact",
                      "id": 9,
                      "organization_id": 3,
                      "title": "Eva Novak",
                      "client": "Aenze",
                      "subtitle": "eva@aenze.com"
                    }
                  ]
                }
              },
              "total": 3
            }
            """.data(using: .utf8)!
            return (response, data)
        }

        let result = try await client.globalSearch(accessToken: "fdm_at_test", query: "vpn")

        XCTAssertEqual(result.total, 3)
        XCTAssertEqual(result.sections["open_tickets"]?.definition?.label, "Open tickets")
        XCTAssertEqual(result.sections["open_tickets"]?.items.first?.code, "TK-10042")
        XCTAssertEqual(result.sections["clients"]?.items.first?.title, "Aenze")
        XCTAssertEqual(result.sections["contacts"]?.items.first?.organizationId, 3)
        XCTAssertEqual(result.sections["contacts"]?.items.first?.client, "Aenze")
    }

    func testNotificationsSendsBearerAndDecodesItems() async throws {
        let client = FoxDeskAPIClient(
            environment: FoxDeskEnvironment(baseURL: URL(string: "https://app.foxdesk.net/index.php")!),
            session: makeStubbedSession()
        )
        URLProtocolStub.requestHandler = { request in
            XCTAssertEqual(request.httpMethod, "GET")
            XCTAssertEqual(request.value(forHTTPHeaderField: "Authorization"), "Bearer fdm_at_test")
            Self.assertAPIPath(request.url, "/api/mobile/v1/notifications")
            XCTAssertEqual(request.url?.query?.contains("limit=25"), true)
            XCTAssertEqual(request.url?.query?.contains("offset=0"), true)
            XCTAssertEqual(request.url?.query?.contains("include_resolved=1"), true)

            let response = HTTPURLResponse(
                url: request.url!,
                statusCode: 200,
                httpVersion: nil,
                headerFields: ["Content-Type": "application/json"]
            )!
            let data = """
            {
              "success": true,
              "data": {
                "unread_count": 2,
                "items": [
                  {
                    "id": 101,
                    "type": "ticket_reply",
                    "ticket_id": 42,
                    "is_read": false,
                    "is_resolved": false,
                    "created_at": "2026-07-05 09:35:00",
                    "time_ago": "2 min ago",
                    "text": "New reply on VPN access stopped working",
                    "action_text": "Open ticket",
                    "snippet": "VPN rejects MFA codes",
                    "is_action": true,
                    "actor": {
                      "name": "Eva Novak",
                      "email": "eva@example.test",
                      "avatar": "https://app.foxdesk.net/uploads/eva.jpg"
                    }
                  }
                ],
                "pagination": {"limit": 25, "offset": 0, "total": 1, "has_more": false}
              },
              "meta": {"schema_version": 1, "resource": "notifications"},
              "errors": []
            }
            """.data(using: .utf8)!
            return (response, data)
        }

        let result = try await client.notifications(
            accessToken: "fdm_at_test",
            includeResolved: true
        )

        XCTAssertEqual(result.data.unreadCount, 2)
        XCTAssertEqual(result.data.items.first?.id, 101)
        XCTAssertEqual(result.data.items.first?.ticketId, 42)
        XCTAssertEqual(result.data.items.first?.actionText, "Open ticket")
        XCTAssertEqual(result.data.items.first?.actor?.name, "Eva Novak")
        XCTAssertEqual(result.data.pagination?.total, 1)
    }

    func testNotificationsSendsCustomLimitAndOffsetForPagination() async throws {
        let client = FoxDeskAPIClient(
            environment: FoxDeskEnvironment(baseURL: URL(string: "https://app.foxdesk.net/index.php")!),
            session: makeStubbedSession()
        )
        URLProtocolStub.requestHandler = { request in
            XCTAssertEqual(request.httpMethod, "GET")
            XCTAssertEqual(request.value(forHTTPHeaderField: "Authorization"), "Bearer fdm_at_test")
            Self.assertAPIPath(request.url, "/api/mobile/v1/notifications")
            XCTAssertEqual(request.url?.query?.contains("limit=10"), true)
            XCTAssertEqual(request.url?.query?.contains("offset=25"), true)
            XCTAssertEqual(request.url?.query?.contains("include_resolved=0"), true)

            let response = HTTPURLResponse(
                url: request.url!,
                statusCode: 200,
                httpVersion: nil,
                headerFields: ["Content-Type": "application/json"]
            )!
            let data = """
            {
              "success": true,
              "data": {
                "unread_count": 0,
                "items": [],
                "pagination": {"limit": 10, "offset": 25, "total": 40, "has_more": true}
              },
              "meta": {"schema_version": 1, "resource": "notifications"},
              "errors": []
            }
            """.data(using: .utf8)!
            return (response, data)
        }

        let result = try await client.notifications(
            accessToken: "fdm_at_test",
            limit: 10,
            offset: 25
        )

        XCTAssertEqual(result.data.pagination?.offset, 25)
        XCTAssertEqual(result.data.pagination?.hasMore, true)
    }

    func testNotificationReadStateSendsPostBody() async throws {
        let client = FoxDeskAPIClient(
            environment: FoxDeskEnvironment(baseURL: URL(string: "https://app.foxdesk.net/index.php")!),
            session: makeStubbedSession()
        )
        URLProtocolStub.requestHandler = { request in
            XCTAssertEqual(request.httpMethod, "POST")
            XCTAssertEqual(request.value(forHTTPHeaderField: "Authorization"), "Bearer fdm_at_test")
            Self.assertAPIPath(request.url, "/api/mobile/v1/notifications/read-state")

            let body = try XCTUnwrap(Self.bodyData(from: request))
            let json = try XCTUnwrap(JSONSerialization.jsonObject(with: body) as? [String: Any])
            XCTAssertEqual(json["scope"] as? String, "notification")
            XCTAssertEqual(json["notification_id"] as? Int, 101)
            XCTAssertEqual(json["is_read"] as? Bool, true)

            let response = HTTPURLResponse(
                url: request.url!,
                statusCode: 200,
                httpVersion: nil,
                headerFields: ["Content-Type": "application/json"]
            )!
            let data = """
            {
              "success": true,
              "data": {
                "unread_count": 1,
                "updated": true
              },
              "meta": {"schema_version": 1, "resource": "notification_read_state"},
              "errors": []
            }
            """.data(using: .utf8)!
            return (response, data)
        }

        let result = try await client.setNotificationReadState(
            accessToken: "fdm_at_test",
            request: NotificationReadStateRequest(
                scope: "notification",
                notificationId: 101,
                isRead: true
            )
        )

        XCTAssertEqual(result.data.unreadCount, 1)
        XCTAssertEqual(result.data.updated, true)
    }

    func testTicketDetailDecodesCommentsTimeAndAttachments() async throws {
        let client = FoxDeskAPIClient(
            environment: FoxDeskEnvironment(baseURL: URL(string: "https://app.foxdesk.net/index.php")!),
            session: makeStubbedSession()
        )
        URLProtocolStub.requestHandler = { request in
            Self.assertAPIPath(request.url, "/api/mobile/v1/tickets/42")

            let response = HTTPURLResponse(
                url: request.url!,
                statusCode: 200,
                httpVersion: nil,
                headerFields: ["Content-Type": "application/json"]
            )!
            let data = """
            {
              "success": true,
              "data": {
                "ticket": {"id": 42, "title": "VPN access stopped working", "code": "TK-10042"},
                "comments": [
                  {"id": 7, "user_id": 2, "author_name": "Emma Carter", "content_text": "Checked VPN profile.", "is_internal": false, "created_at": "2026-07-05 09:35:00"}
                ],
                "attachments": [
                  {"id": 9, "ticket_id": 42, "filename": "screenshot.png", "mime_type": "image/png", "file_size_label": "120 KB", "can_preview": true}
                ],
                "time_entries": [
                  {"id": 11, "comment_id": 7, "user_name": "Emma Carter", "duration_minutes": 25, "summary": "VPN profile check", "is_billable": true}
                ],
                "actions": null
              },
              "meta": {"schema_version": 1, "resource": "ticket_detail"},
              "errors": []
            }
            """.data(using: .utf8)!
            return (response, data)
        }

        let result = try await client.ticketDetail(accessToken: "fdm_at_test", ticketId: 42)

        XCTAssertEqual(result.data.ticket.code, "TK-10042")
        XCTAssertEqual(result.data.comments.first?.authorName, "Emma Carter")
        XCTAssertEqual(result.data.attachments.first?.filename, "screenshot.png")
        XCTAssertEqual(result.data.timeEntries.first?.commentId, 7)
        XCTAssertEqual(result.data.timeEntries.first?.durationMinutes, 25)
    }

    func testAddCommentWithTimeUsesDedicatedAction() async throws {
        let client = FoxDeskAPIClient(
            environment: FoxDeskEnvironment(baseURL: URL(string: "https://app.foxdesk.net/index.php")!),
            session: makeStubbedSession()
        )
        URLProtocolStub.requestHandler = { request in
            XCTAssertEqual(request.httpMethod, "POST")
            Self.assertAPIPath(request.url, "/api/mobile/v1/tickets/42/comment-with-time")
            let body = try XCTUnwrap(Self.bodyData(from: request))
            let json = try XCTUnwrap(JSONSerialization.jsonObject(with: body) as? [String: Any])
            XCTAssertEqual(json["ticket_id"] as? Int, 42)
            XCTAssertEqual(json["duration_minutes"] as? Int, 25)
            XCTAssertEqual(json["is_internal"] as? Bool, false)
            XCTAssertEqual(json["manual_date"] as? String, "2026-07-05")
            XCTAssertEqual(json["manual_start_time"] as? String, "09:10")
            XCTAssertEqual(json["manual_end_time"] as? String, "09:35")
            XCTAssertEqual(json["created_at"] as? String, "2026-07-05 09:35:00")

            let response = HTTPURLResponse(
                url: request.url!,
                statusCode: 200,
                httpVersion: nil,
                headerFields: ["Content-Type": "application/json"]
            )!
            let data = """
            {
              "success": true,
              "data": {
                "ticket_id": 42,
                "comment_id": 100,
                "time_entry_id": 200,
                "duration_minutes": 25,
                "started_at": "2026-07-05 09:10:00",
                "ended_at": "2026-07-05 09:35:00"
              },
              "meta": {"schema_version": 1, "resource": "ticket_comment"},
              "errors": []
            }
            """.data(using: .utf8)!
            return (response, data)
        }

        let result = try await client.addComment(
            accessToken: "fdm_at_test",
            request: AddCommentRequest(
                ticketId: 42,
                content: "<p>Checked VPN profile.</p>",
                isInternal: false,
                durationMinutes: 25,
                isBillable: true,
                timeSummary: "Checked VPN profile.",
                manualDate: "2026-07-05",
                manualStartTime: "09:10",
                manualEndTime: "09:35",
                createdAt: "2026-07-05 09:35:00"
            )
        )

        XCTAssertEqual(result.data.commentId, 100)
        XCTAssertEqual(result.data.timeEntryId, 200)
    }

    func testTicketActionsLoadsActionSurfaceWithoutFullDetail() async throws {
        let client = FoxDeskAPIClient(
            environment: FoxDeskEnvironment(baseURL: URL(string: "https://app.foxdesk.net/index.php")!),
            session: makeStubbedSession()
        )
        URLProtocolStub.requestHandler = { request in
            XCTAssertEqual(request.httpMethod, "GET")
            XCTAssertEqual(request.value(forHTTPHeaderField: "Authorization"), "Bearer fdm_at_test")
            Self.assertAPIPath(request.url, "/api/mobile/v1/tickets/42/actions")

            let response = HTTPURLResponse(
                url: request.url!,
                statusCode: 200,
                httpVersion: nil,
                headerFields: ["Content-Type": "application/json"]
            )!
            let data = """
            {
              "success": true,
              "data": {
                "ticket": {"id": 42, "title": "VPN access stopped working", "code": "TK-10042"},
                "actions": {
                  "primary": [{"key": "reply", "label": "Reply", "variant": "primary"}],
                  "statuses": [{"id": 2, "name": "In progress", "group": "open", "is_closed": false}],
                  "priorities": [{"id": 1, "name": "High"}],
                  "assignees": [{"id": 7, "name": "Emma Carter", "email": "emma@example.test", "role": "agent"}],
                  "timer": {"state": "stopped", "elapsed_minutes": 0, "elapsed_label": "0 min"}
                }
              },
              "meta": {"schema_version": 1, "resource": "ticket_actions"},
              "errors": []
            }
            """.data(using: .utf8)!
            return (response, data)
        }

        let result = try await client.ticketActions(accessToken: "fdm_at_test", ticketId: 42)

        XCTAssertEqual(result.data.ticket.code, "TK-10042")
        XCTAssertEqual(result.data.actions.primary?.first?.key, "reply")
        XCTAssertEqual(result.data.actions.statuses?.first?.name, "In progress")
        XCTAssertEqual(result.data.actions.assignees?.first?.name, "Emma Carter")
    }

    func testUpdateTicketPostsWorkflowFieldsAndExplicitNullAssignee() async throws {
        let client = FoxDeskAPIClient(
            environment: FoxDeskEnvironment(baseURL: URL(string: "https://app.foxdesk.net/index.php")!),
            session: makeStubbedSession()
        )
        URLProtocolStub.requestHandler = { request in
            XCTAssertEqual(request.httpMethod, "POST")
            XCTAssertEqual(request.value(forHTTPHeaderField: "Authorization"), "Bearer fdm_at_test")
            Self.assertAPIPath(request.url, "/api/mobile/v1/tickets/42")

            let body = try XCTUnwrap(Self.bodyData(from: request))
            let json = try XCTUnwrap(JSONSerialization.jsonObject(with: body) as? [String: Any])
            XCTAssertEqual(json["ticket_id"] as? Int, 42)
            XCTAssertEqual(json["status_id"] as? Int, 5)
            XCTAssertEqual(json["priority_id"] as? Int, 2)
            XCTAssertTrue(json.keys.contains("assignee_id"))
            XCTAssertTrue(json["assignee_id"] is NSNull)

            let response = HTTPURLResponse(
                url: request.url!,
                statusCode: 200,
                httpVersion: nil,
                headerFields: ["Content-Type": "application/json"]
            )!
            let data = """
            {
              "success": true,
              "data": {
                "ticket": {
                  "id": 42,
                  "title": "VPN access stopped working",
                  "code": "TK-10042",
                  "status": {"id": 5, "name": "Done", "group": "done", "is_closed": true},
                  "priority": {"id": 2, "name": "Medium"}
                },
                "actions": {
                  "statuses": [{"id": 5, "name": "Done", "group": "done", "is_closed": true}],
                  "priorities": [{"id": 2, "name": "Medium"}],
                  "assignees": [{"id": 3, "name": "Emma Carter", "email": "emma@example.test", "role": "agent"}]
                },
                "updated_fields": ["status_id", "priority_id", "assignee_id"]
              },
              "meta": {"schema_version": 1, "resource": "update_ticket"},
              "errors": []
            }
            """.data(using: .utf8)!
            return (response, data)
        }

        let result = try await client.updateTicket(
            accessToken: "fdm_at_test",
            request: UpdateTicketRequest(
                ticketId: 42,
                statusId: 5,
                priorityId: 2,
                includePriorityId: true,
                assigneeId: nil,
                includeAssigneeId: true
            )
        )

        XCTAssertEqual(result.data.ticket.status?.name, "Done")
        XCTAssertEqual(result.data.actions?.statuses?.first?.id, 5)
        XCTAssertEqual(result.data.updatedFields, ["status_id", "priority_id", "assignee_id"])
    }

    func testTimerActionPostsNativeTimerAction() async throws {
        let client = FoxDeskAPIClient(
            environment: FoxDeskEnvironment(baseURL: URL(string: "https://app.foxdesk.net/index.php")!),
            session: makeStubbedSession()
        )
        URLProtocolStub.requestHandler = { request in
            XCTAssertEqual(request.httpMethod, "POST")
            XCTAssertEqual(request.value(forHTTPHeaderField: "Authorization"), "Bearer fdm_at_test")
            Self.assertAPIPath(request.url, "/api/mobile/v1/tickets/42/timer")

            let body = try XCTUnwrap(Self.bodyData(from: request))
            let json = try XCTUnwrap(JSONSerialization.jsonObject(with: body) as? [String: Any])
            XCTAssertEqual(json["ticket_id"] as? Int, 42)
            XCTAssertEqual(json["action"] as? String, "start")

            let response = HTTPURLResponse(
                url: request.url!,
                statusCode: 200,
                httpVersion: nil,
                headerFields: ["Content-Type": "application/json"]
            )!
            let data = """
            {
              "success": true,
              "data": {
                "ticket": {"id": 42, "title": "VPN access stopped working", "code": "TK-10042"},
                "timer": {"state": "running", "entry_id": 77, "elapsed_minutes": 0, "elapsed_label": "0 min"},
                "action": "start",
                "result": {"success": true, "entry_id": 77}
              },
              "meta": {"schema_version": 1, "resource": "timer_action"},
              "errors": []
            }
            """.data(using: .utf8)!
            return (response, data)
        }

        let result = try await client.ticketTimerAction(accessToken: "fdm_at_test", ticketId: 42, action: "start")

        XCTAssertEqual(result.data.timer.state, "running")
        XCTAssertEqual(result.data.timer.entryId, 77)
        XCTAssertEqual(result.data.action, "start")
    }

    func testAttachmentMetadataDecodesAuthorizedURLs() async throws {
        let client = FoxDeskAPIClient(
            environment: FoxDeskEnvironment(baseURL: URL(string: "https://app.foxdesk.net/index.php")!),
            session: makeStubbedSession()
        )
        URLProtocolStub.requestHandler = { request in
            XCTAssertEqual(request.httpMethod, "GET")
            Self.assertAPIPath(request.url, "/api/mobile/v1/attachments/9")

            let response = HTTPURLResponse(
                url: request.url!,
                statusCode: 200,
                httpVersion: nil,
                headerFields: ["Content-Type": "application/json"]
            )!
            let data = """
            {
              "success": true,
              "data": {
                "attachment": {
                  "id": 9,
                  "ticket_id": 42,
                  "filename": "screenshot.png",
                  "mime_type": "image/png",
                  "file_size": 120000,
                  "file_size_label": "120 KB",
                  "storage_driver": "r2",
                  "download_url": "attachment.php?id=9",
                  "preview_url": "attachment.php?id=9",
                  "can_preview": true,
                  "created_at": "2026-07-05 09:35:00"
                }
              },
              "meta": {"schema_version": 1, "resource": "attachment_metadata"},
              "errors": []
            }
            """.data(using: .utf8)!
            return (response, data)
        }

        let result = try await client.attachmentMetadata(accessToken: "fdm_at_test", attachmentId: 9)

        XCTAssertEqual(result.data.attachment.filename, "screenshot.png")
        XCTAssertEqual(result.data.attachment.canPreview, true)
        XCTAssertEqual(client.resourceURL(from: result.data.attachment.downloadUrl)?.absoluteString, "https://app.foxdesk.net/attachment.php?id=9")
    }

    func testUploadAttachmentUsesMultipartFormData() async throws {
        let client = FoxDeskAPIClient(
            environment: FoxDeskEnvironment(baseURL: URL(string: "https://app.foxdesk.net/index.php")!),
            session: makeStubbedSession()
        )
        URLProtocolStub.requestHandler = { request in
            XCTAssertEqual(request.httpMethod, "POST")
            XCTAssertEqual(request.value(forHTTPHeaderField: "Authorization"), "Bearer fdm_at_test")
            Self.assertAPIPath(request.url, "/api/mobile/v1/tickets/42/attachments")
            XCTAssertTrue(request.value(forHTTPHeaderField: "Content-Type")?.contains("multipart/form-data") == true)

            let body = try XCTUnwrap(Self.bodyData(from: request))
            let bodyText = String(decoding: body, as: UTF8.self)
            XCTAssertFalse(bodyText.contains("name=\"ticket_id\""))
            XCTAssertTrue(bodyText.contains("name=\"file\"; filename=\"screenshot.png\""))
            XCTAssertTrue(bodyText.contains("Content-Type: image/png"))

            let response = HTTPURLResponse(
                url: request.url!,
                statusCode: 200,
                httpVersion: nil,
                headerFields: ["Content-Type": "application/json"]
            )!
            let data = """
            {
              "success": true,
              "file": {
                "filename": "uploads/2026/07/screenshot.png",
                "original_name": "screenshot.png",
                "mime_type": "image/png",
                "file_size": 4,
                "url": "attachment.php?id=9",
                "attachment_id": 9
              }
            }
            """.data(using: .utf8)!
            return (response, data)
        }

        let result = try await client.uploadAttachment(
            accessToken: "fdm_at_test",
            ticketId: 42,
            filename: "screenshot.png",
            mimeType: "image/png",
            data: Data([0x89, 0x50, 0x4E, 0x47])
        )

        XCTAssertEqual(result.file.attachmentId, 9)
        XCTAssertEqual(result.file.originalName, "screenshot.png")
    }

    func testAgentTicketWorkflowSmokeUsesVersionedMobileEndpoints() async throws {
        let client = FoxDeskAPIClient(
            environment: FoxDeskEnvironment(baseURL: URL(string: "https://app.foxdesk.net/index.php")!),
            session: makeStubbedSession()
        )

        var step = 0
        URLProtocolStub.requestHandler = { request in
            defer { step += 1 }

            let response = HTTPURLResponse(
                url: try XCTUnwrap(request.url),
                statusCode: 200,
                httpVersion: nil,
                headerFields: ["Content-Type": "application/json"]
            )!

            switch step {
            case 0:
                XCTAssertEqual(request.httpMethod, "GET")
                XCTAssertEqual(request.value(forHTTPHeaderField: "Authorization"), "Bearer fdm_at_test")
                Self.assertAPIPath(request.url, "/api/mobile/v1/tickets/create-options")
                let data = """
                {
                  "success": true,
                  "data": {
                    "clients": [
                      {"id": 3, "name": "Aenze", "email": "support@aenze.com", "is_active": true}
                    ],
                    "statuses": [
                      {"id": 1, "name": "New", "color": "#335CFF", "group": "open", "is_closed": false}
                    ],
                    "priorities": [
                      {"id": 2, "name": "Medium", "color": "#3b82f6"}
                    ],
                    "assignees": [
                      {"id": 7, "name": "Emma Carter", "email": "emma@example.com", "role": "agent"}
                    ],
                    "defaults": {"status_id": 1, "priority_id": 2, "assignee_id": 7}
                  },
                  "meta": {"schema_version": 1, "resource": "ticket_create_options"},
                  "errors": []
                }
                """.data(using: .utf8)!
                return (response, data)

            case 1:
                XCTAssertEqual(request.httpMethod, "POST")
                XCTAssertEqual(request.value(forHTTPHeaderField: "Authorization"), "Bearer fdm_at_test")
                Self.assertAPIPath(request.url, "/api/mobile/v1/tickets")
                let body = try XCTUnwrap(Self.bodyData(from: request))
                let json = try XCTUnwrap(JSONSerialization.jsonObject(with: body) as? [String: Any])
                XCTAssertEqual(json["organization_id"] as? Int, 3)
                XCTAssertEqual(json["assignee_id"] as? Int, 7)
                XCTAssertEqual(json["priority_id"] as? Int, 2)
                XCTAssertEqual(json["status_id"] as? Int, 1)
                XCTAssertEqual(json["due_date"] as? String, "2026-07-08")
                let data = """
                {
                  "success": true,
                  "data": {
                    "ticket_id": 42,
                    "ticket_hash": "abc",
                    "ticket_code": "TK-10042",
                    "ticket": {
                      "id": 42,
                      "hash": "abc",
                      "code": "TK-10042",
                      "title": "VPN access stopped working",
                      "status": {"id": 1, "name": "New", "color": "#335CFF", "group": "open", "is_closed": false},
                      "client": {"id": 3, "name": "Aenze"}
                    }
                  },
                  "meta": {"schema_version": 1, "resource": "ticket"},
                  "errors": []
                }
                """.data(using: .utf8)!
                return (response, data)

            case 2:
                XCTAssertEqual(request.httpMethod, "GET")
                XCTAssertEqual(request.value(forHTTPHeaderField: "Authorization"), "Bearer fdm_at_test")
                Self.assertAPIPath(request.url, "/api/mobile/v1/tickets/42")
                let data = """
                {
                  "success": true,
                  "data": {
                    "ticket": {
                      "id": 42,
                      "hash": "abc",
                      "code": "TK-10042",
                      "title": "VPN access stopped working",
                      "status": {"id": 1, "name": "New", "color": "#335CFF", "group": "open", "is_closed": false},
                      "client": {"id": 3, "name": "Aenze"}
                    },
                    "comments": [
                      {"id": 7, "user_id": 2, "author_name": "Emma Carter", "content_html": "<p>Initial triage.</p>", "content_text": "Initial triage.", "is_internal": false, "created_at": "2026-07-05 09:35:00"}
                    ],
                    "attachments": [],
                    "time_entries": [],
                    "actions": {
                      "primary": [{"key": "reply", "label": "Reply", "icon": "message", "variant": "primary"}],
                      "statuses": [{"id": 1, "name": "New", "color": "#335CFF", "group": "open", "is_closed": false}],
                      "priorities": [{"id": 2, "name": "Medium", "color": "#3b82f6"}],
                      "assignees": [{"id": 7, "name": "Emma Carter", "email": "emma@example.com", "role": "agent"}],
                      "timer": {"state": "stopped", "entry_id": null, "elapsed_minutes": 0, "elapsed_label": "0 min"}
                    }
                  },
                  "meta": {"schema_version": 1, "resource": "ticket_detail"},
                  "errors": []
                }
                """.data(using: .utf8)!
                return (response, data)

            case 3:
                XCTAssertEqual(request.httpMethod, "POST")
                XCTAssertEqual(request.value(forHTTPHeaderField: "Authorization"), "Bearer fdm_at_test")
                Self.assertAPIPath(request.url, "/api/mobile/v1/tickets/42/comment-with-time")
                let body = try XCTUnwrap(Self.bodyData(from: request))
                let json = try XCTUnwrap(JSONSerialization.jsonObject(with: body) as? [String: Any])
                XCTAssertEqual(json["duration_minutes"] as? Int, 48)
                XCTAssertEqual(json["manual_date"] as? String, "2026-07-05")
                XCTAssertEqual(json["manual_start_time"] as? String, "21:18")
                XCTAssertEqual(json["manual_end_time"] as? String, "22:06")
                XCTAssertEqual(json["is_billable"] as? Bool, true)
                let data = """
                {
                  "success": true,
                  "data": {
                    "ticket_id": 42,
                    "comment_id": 100,
                    "time_entry_id": 200,
                    "duration_minutes": 48,
                    "started_at": "2026-07-05 21:18:00",
                    "ended_at": "2026-07-05 22:06:00"
                  },
                  "meta": {"schema_version": 1, "resource": "ticket_comment"},
                  "errors": []
                }
                """.data(using: .utf8)!
                return (response, data)

            case 4:
                XCTAssertEqual(request.httpMethod, "POST")
                XCTAssertEqual(request.value(forHTTPHeaderField: "Authorization"), "Bearer fdm_at_test")
                Self.assertAPIPath(request.url, "/api/mobile/v1/tickets/42/attachments")
                XCTAssertTrue(request.value(forHTTPHeaderField: "Content-Type")?.contains("multipart/form-data") == true)
                let body = try XCTUnwrap(Self.bodyData(from: request))
                let bodyText = String(decoding: body, as: UTF8.self)
                XCTAssertTrue(bodyText.contains("name=\"file\"; filename=\"evidence.png\""))
                let data = """
                {
                  "success": true,
                  "file": {
                    "filename": "uploads/2026/07/evidence.png",
                    "original_name": "evidence.png",
                    "mime_type": "image/png",
                    "file_size": 4,
                    "url": "attachment.php?id=9",
                    "attachment_id": 9
                  }
                }
                """.data(using: .utf8)!
                return (response, data)

            case 5:
                XCTAssertEqual(request.httpMethod, "GET")
                XCTAssertEqual(request.value(forHTTPHeaderField: "Authorization"), "Bearer fdm_at_test")
                Self.assertAPIPath(request.url, "/api/mobile/v1/attachments/9")
                let data = """
                {
                  "success": true,
                  "data": {
                    "attachment": {
                      "id": 9,
                      "ticket_id": 42,
                      "comment_id": 100,
                      "filename": "evidence.png",
                      "mime_type": "image/png",
                      "file_size": 4,
                      "file_size_label": "4 B",
                      "download_url": "attachment.php?id=9",
                      "preview_url": "attachment.php?id=9",
                      "can_preview": true
                    }
                  },
                  "meta": {"schema_version": 1, "resource": "attachment_metadata"},
                  "errors": []
                }
                """.data(using: .utf8)!
                return (response, data)

            default:
                XCTFail("Unexpected API request at step \(step): \(request.url?.absoluteString ?? "")")
                let data = #"{"success":false,"errors":[{"message":"Unexpected request"}]}"#.data(using: .utf8)!
                return (response, data)
            }
        }

        let options = try await client.createTicketOptions(accessToken: "fdm_at_test")
        XCTAssertEqual(options.data.clients.first?.id, 3)

        let created = try await client.createTicket(
            accessToken: "fdm_at_test",
            request: CreateTicketRequest(
                title: "VPN access stopped working",
                description: "<p>VPN rejects MFA codes.</p>",
                organizationId: options.data.clients.first?.id,
                assigneeId: options.data.defaults?.assigneeId,
                priorityId: options.data.defaults?.priorityId,
                statusId: options.data.defaults?.statusId,
                dueDate: "2026-07-08"
            )
        )
        XCTAssertEqual(created.data.ticketId, 42)

        let detail = try await client.ticketDetail(accessToken: "fdm_at_test", ticketId: created.data.ticketId)
        XCTAssertEqual(detail.data.actions?.primary?.first?.key, "reply")

        let comment = try await client.addComment(
            accessToken: "fdm_at_test",
            request: AddCommentRequest(
                ticketId: created.data.ticketId,
                content: "<p><strong>Checked VPN profile</strong></p>",
                isInternal: false,
                durationMinutes: 48,
                isBillable: true,
                timeSummary: "Checked VPN profile",
                manualDate: "2026-07-05",
                manualStartTime: "21:18",
                manualEndTime: "22:06"
            )
        )
        XCTAssertEqual(comment.data.commentId, 100)
        XCTAssertEqual(comment.data.timeEntryId, 200)

        let upload = try await client.uploadAttachment(
            accessToken: "fdm_at_test",
            ticketId: created.data.ticketId,
            filename: "evidence.png",
            mimeType: "image/png",
            data: Data([0x89, 0x50, 0x4E, 0x47])
        )
        XCTAssertEqual(upload.file.attachmentId, 9)

        let attachment = try await client.attachmentMetadata(accessToken: "fdm_at_test", attachmentId: 9)
        XCTAssertEqual(attachment.data.attachment.commentId, 100)
        XCTAssertEqual(attachment.data.attachment.canPreview, true)
        XCTAssertEqual(step, 6)
    }

    func testRegisterDevicePostsAPNsToken() async throws {
        let client = FoxDeskAPIClient(
            environment: FoxDeskEnvironment(baseURL: URL(string: "https://app.foxdesk.net/index.php")!),
            session: makeStubbedSession()
        )
        URLProtocolStub.requestHandler = { request in
            XCTAssertEqual(request.httpMethod, "POST")
            XCTAssertEqual(request.value(forHTTPHeaderField: "Authorization"), "Bearer fdm_at_test")
            Self.assertAPIPath(request.url, "/api/mobile/v1/device-token")

            let body = try XCTUnwrap(Self.bodyData(from: request))
            let json = try XCTUnwrap(JSONSerialization.jsonObject(with: body) as? [String: Any])
            XCTAssertEqual(json["apns_device_token"] as? String, String(repeating: "a", count: 64))
            XCTAssertEqual(json["apns_environment"] as? String, "sandbox")
            XCTAssertEqual(json["device_id"] as? String, "device")

            let response = HTTPURLResponse(
                url: request.url!,
                statusCode: 200,
                httpVersion: nil,
                headerFields: ["Content-Type": "application/json"]
            )!
            let data = """
            {
              "success": true,
              "device_id": 12,
              "registered": true
            }
            """.data(using: .utf8)!
            return (response, data)
        }

        let result = try await client.registerDevice(
            accessToken: "fdm_at_test",
            apnsToken: String(repeating: "a", count: 64),
            environment: .sandbox,
            device: DeviceContext(deviceId: "device", deviceName: "iPhone", appVersion: "0.1")
        )

        XCTAssertEqual(result.deviceId, 12)
        XCTAssertEqual(result.registered, true)
    }

    func testUnregisterDevicePostsDeviceID() async throws {
        let client = FoxDeskAPIClient(
            environment: FoxDeskEnvironment(baseURL: URL(string: "https://app.foxdesk.net/index.php")!),
            session: makeStubbedSession()
        )
        URLProtocolStub.requestHandler = { request in
            XCTAssertEqual(request.httpMethod, "POST")
            XCTAssertEqual(request.value(forHTTPHeaderField: "Authorization"), "Bearer fdm_at_test")
            Self.assertAPIPath(request.url, "/api/mobile/v1/device-token/unregister")

            let body = try XCTUnwrap(Self.bodyData(from: request))
            let json = try XCTUnwrap(JSONSerialization.jsonObject(with: body) as? [String: Any])
            XCTAssertEqual(json["device_id"] as? String, "device")

            let response = HTTPURLResponse(
                url: request.url!,
                statusCode: 200,
                httpVersion: nil,
                headerFields: ["Content-Type": "application/json"]
            )!
            let data = """
            {
              "success": true,
              "unregistered": true
            }
            """.data(using: .utf8)!
            return (response, data)
        }

        let result = try await client.unregisterDevice(
            accessToken: "fdm_at_test",
            device: DeviceContext(deviceId: "device", deviceName: "iPhone", appVersion: "0.1")
        )

        XCTAssertEqual(result.unregistered, true)
    }

    func testDownloadResourceSendsBearerOnlyToFoxDeskHost() async throws {
        let client = FoxDeskAPIClient(
            environment: FoxDeskEnvironment(baseURL: URL(string: "https://app.foxdesk.net/index.php")!),
            session: makeStubbedSession()
        )

        var requests: [URLRequest] = []
        URLProtocolStub.requestHandler = { request in
            requests.append(request)
            let response = HTTPURLResponse(
                url: request.url!,
                statusCode: 200,
                httpVersion: nil,
                headerFields: ["Content-Type": "application/octet-stream"]
            )!
            return (response, Data("file".utf8))
        }

        let sameHostData = try await client.downloadResource(
            accessToken: "fdm_at_test",
            url: URL(string: "https://app.foxdesk.net/index.php?page=api&action=download")!
        )
        let externalData = try await client.downloadResource(
            accessToken: "fdm_at_test",
            url: URL(string: "https://example-r2.invalid/file")!
        )

        XCTAssertEqual(String(data: sameHostData, encoding: .utf8), "file")
        XCTAssertEqual(String(data: externalData, encoding: .utf8), "file")
        XCTAssertEqual(requests.first?.value(forHTTPHeaderField: "Authorization"), "Bearer fdm_at_test")
        XCTAssertNil(requests.last?.value(forHTTPHeaderField: "Authorization"))
    }

    func testNotificationPayloadExtractsTicketIDVariants() {
        XCTAssertEqual(
            FoxDeskNotificationPayload.ticketID(from: ["ticket_id": 42]),
            42
        )
        XCTAssertEqual(
            FoxDeskNotificationPayload.ticketID(from: ["ticketId": "43"]),
            43
        )
        XCTAssertEqual(
            FoxDeskNotificationPayload.ticketID(from: ["data": ["ticket_id": "44"]]),
            44
        )
        XCTAssertEqual(
            FoxDeskNotificationPayload.ticketID(from: [
                "aps": ["alert": ["title": "Ticket updated"]],
                "ticket_id": "45",
            ]),
            45
        )
        XCTAssertNil(
            FoxDeskNotificationPayload.ticketID(from: ["data": ["client_id": 12]])
        )
        XCTAssertNil(
            FoxDeskNotificationPayload.ticketID(from: ["ticket_id": 0])
        )
        XCTAssertNil(
            FoxDeskNotificationPayload.ticketID(from: ["data": ["ticket_id": "-2"]])
        )
    }

    func testMobileRichTextFormatterBuildsParagraphsAndLists() {
        let html = MobileRichTextFormatter.html(from: """
        Intro paragraph

        - **VPN** profile checked
        - _MFA_ reset

        1. Customer notified
        """)

        XCTAssertEqual(
            html,
            "<p>Intro paragraph</p><ul><li><strong>VPN</strong> profile checked</li><li><em>MFA</em> reset</li></ul><ol><li>Customer notified</li></ol>"
        )
    }

    func testMobileRichTextFormatterEscapesHTMLInput() {
        let html = MobileRichTextFormatter.html(from: "<script>alert(1)</script>\n\nSafe & sound")

        XCTAssertEqual(
            html,
            "<p>&lt;script&gt;alert(1)&lt;/script&gt;</p><p>Safe &amp; sound</p>"
        )
    }

    func testStagedAttachmentUploadStateKeepsOnlyFailedUploadsForRetry() {
        struct StagedAttachment: Sendable, Equatable {
            let id: UUID
            let filename: String
        }

        let first = StagedAttachment(id: UUID(), filename: "first.png")
        let second = StagedAttachment(id: UUID(), filename: "second.pdf")
        let third = StagedAttachment(id: UUID(), filename: "third.jpg")
        var uploadState = StagedAttachmentUploadState<UUID>()

        uploadState.markUploaded(first.id)
        uploadState.markUploaded(third.id)

        XCTAssertTrue(uploadState.hasUploaded(first.id))
        XCTAssertEqual(uploadState.uploadedCount, 2)
        XCTAssertEqual(
            uploadState.remaining(from: [first, second, third]) { $0.id },
            [second]
        )
    }

    func testTicketCommentDraftStorePersistsPerTicketAndUser() async throws {
        let suiteName = "FoxDeskDraftStoreTests-\(UUID().uuidString)"
        let defaults = try XCTUnwrap(UserDefaults(suiteName: suiteName))
        defer { defaults.removePersistentDomain(forName: suiteName) }

        let store = TicketCommentDraftStore(defaults: defaults)
        let draft = TicketCommentDraft(
            ticketId: 42,
            userId: 7,
            content: "Checked VPN profile",
            isInternal: true,
            includeTime: true,
            useExactTime: true,
            durationMinutes: 48,
            workDate: Date(timeIntervalSince1970: 1_783_200_000),
            startTime: Date(timeIntervalSince1970: 1_783_276_680),
            endTime: Date(timeIntervalSince1970: 1_783_279_560)
        )

        try await store.save(draft)

        let restored = try await store.load(ticketId: 42, userId: 7)
        XCTAssertEqual(restored?.content, "Checked VPN profile")
        XCTAssertEqual(restored?.isInternal, true)
        XCTAssertEqual(restored?.includeTime, true)
        XCTAssertEqual(restored?.durationMinutes, 48)
        let otherTicketDraft = try await store.load(ticketId: 43, userId: 7)
        let otherUserDraft = try await store.load(ticketId: 42, userId: 8)
        XCTAssertNil(otherTicketDraft)
        XCTAssertNil(otherUserDraft)
    }

    func testTicketCommentDraftStoreClearsEmptyAndSubmittedDrafts() async throws {
        let suiteName = "FoxDeskDraftStoreTests-\(UUID().uuidString)"
        let defaults = try XCTUnwrap(UserDefaults(suiteName: suiteName))
        defer { defaults.removePersistentDomain(forName: suiteName) }

        let store = TicketCommentDraftStore(defaults: defaults)
        try await store.save(TicketCommentDraft(ticketId: 42, userId: 7, content: "Temporary note"))
        let savedDraft = try await store.load(ticketId: 42, userId: 7)
        XCTAssertNotNil(savedDraft)

        try await store.save(TicketCommentDraft(ticketId: 42, userId: 7, content: "   \n"))
        let emptyDraft = try await store.load(ticketId: 42, userId: 7)
        XCTAssertNil(emptyDraft)

        try await store.save(TicketCommentDraft(ticketId: 42, userId: 7, content: "Another note"))
        await store.clear(ticketId: 42, userId: 7)
        let clearedDraft = try await store.load(ticketId: 42, userId: 7)
        XCTAssertNil(clearedDraft)
    }

    func testTicketListCacheStorePersistsPerUserAndList() async throws {
        let suiteName = "FoxDeskTicketListCacheTests-\(UUID().uuidString)"
        let defaults = try XCTUnwrap(UserDefaults(suiteName: suiteName))
        defer { defaults.removePersistentDomain(forName: suiteName) }

        let store = TicketListCacheStore(defaults: defaults)
        let ticket = cachedTicket(id: 42, title: "VPN access stopped working")

        try await store.save(
            userId: 7,
            listKey: "view-open-assigned-me-search-vpn",
            tickets: [ticket],
            totalCount: 1
        )

        let restored = try await store.load(userId: 7, listKey: "view-open-assigned-me-search-vpn")
        XCTAssertEqual(restored?.tickets.first?.id, 42)
        XCTAssertEqual(restored?.tickets.first?.title, "VPN access stopped working")
        XCTAssertEqual(restored?.totalCount, 1)

        let otherList = try await store.load(userId: 7, listKey: "view-open-assigned-all-search-vpn")
        let otherUser = try await store.load(userId: 8, listKey: "view-open-assigned-me-search-vpn")
        XCTAssertNil(otherList)
        XCTAssertNil(otherUser)
    }

    func testTicketListCacheStoreClearsSavedLists() async throws {
        let suiteName = "FoxDeskTicketListCacheTests-\(UUID().uuidString)"
        let defaults = try XCTUnwrap(UserDefaults(suiteName: suiteName))
        defer { defaults.removePersistentDomain(forName: suiteName) }

        let store = TicketListCacheStore(defaults: defaults)
        try await store.save(
            userId: 7,
            listKey: "view-waiting-assigned-all-search-",
            tickets: [cachedTicket(id: 55, title: "Waiting ticket")],
            totalCount: 1
        )

        let saved = try await store.load(userId: 7, listKey: "view-waiting-assigned-all-search-")
        XCTAssertNotNil(saved)

        await store.clear(userId: 7, listKey: "view-waiting-assigned-all-search-")
        let cleared = try await store.load(userId: 7, listKey: "view-waiting-assigned-all-search-")
        XCTAssertNil(cleared)
    }

    func testTicketDetailCacheStorePersistsPerUserAndTicket() async throws {
        let suiteName = "FoxDeskTicketDetailCacheTests-\(UUID().uuidString)"
        let defaults = try XCTUnwrap(UserDefaults(suiteName: suiteName))
        defer { defaults.removePersistentDomain(forName: suiteName) }

        let store = TicketDetailCacheStore(defaults: defaults)
        let detail = cachedTicketDetail(id: 42, title: "VPN access stopped working")

        try await store.save(userId: 7, ticketId: 42, detail: detail)

        let restored = try await store.load(userId: 7, ticketId: 42)
        XCTAssertEqual(restored?.detail.ticket.id, 42)
        XCTAssertEqual(restored?.detail.comments.first?.contentText, "Checked VPN profile.")
        XCTAssertEqual(restored?.detail.attachments.first?.filename, "vpn.png")
        XCTAssertEqual(restored?.detail.timeEntries.first?.durationMinutes, 48)

        let otherTicket = try await store.load(userId: 7, ticketId: 43)
        let otherUser = try await store.load(userId: 8, ticketId: 42)
        XCTAssertNil(otherTicket)
        XCTAssertNil(otherUser)
    }

    func testTicketDetailCacheStoreClearsSavedTicket() async throws {
        let suiteName = "FoxDeskTicketDetailCacheTests-\(UUID().uuidString)"
        let defaults = try XCTUnwrap(UserDefaults(suiteName: suiteName))
        defer { defaults.removePersistentDomain(forName: suiteName) }

        let store = TicketDetailCacheStore(defaults: defaults)
        try await store.save(userId: 7, ticketId: 42, detail: cachedTicketDetail(id: 42, title: "VPN"))

        let saved = try await store.load(userId: 7, ticketId: 42)
        XCTAssertNotNil(saved)

        await store.clear(userId: 7, ticketId: 42)
        let cleared = try await store.load(userId: 7, ticketId: 42)
        XCTAssertNil(cleared)
    }

    func testHomeFeedCacheStorePersistsPerUser() async throws {
        let suiteName = "FoxDeskHomeFeedCacheTests-\(UUID().uuidString)"
        let defaults = try XCTUnwrap(UserDefaults(suiteName: suiteName))
        defer { defaults.removePersistentDomain(forName: suiteName) }

        let store = HomeFeedCacheStore(defaults: defaults)
        let home = cachedHomeFeed()

        try await store.save(userId: 7, home: home)

        let restored = try await store.load(userId: 7)
        XCTAssertEqual(restored?.home.work?["mine"]?.items?.first?.title, "VPN access stopped working")
        XCTAssertEqual(restored?.home.timers?.first?.elapsedMinutes, 25)
        XCTAssertEqual(restored?.home.time?.chart?.days?.first?.minutes, 35)
        XCTAssertEqual(restored?.home.notifications?.unreadCount, 3)

        let otherUser = try await store.load(userId: 8)
        XCTAssertNil(otherUser)
    }

    func testHomeFeedCacheStoreClearsSavedFeed() async throws {
        let suiteName = "FoxDeskHomeFeedCacheTests-\(UUID().uuidString)"
        let defaults = try XCTUnwrap(UserDefaults(suiteName: suiteName))
        defer { defaults.removePersistentDomain(forName: suiteName) }

        let store = HomeFeedCacheStore(defaults: defaults)
        try await store.save(userId: 7, home: cachedHomeFeed())

        let saved = try await store.load(userId: 7)
        XCTAssertNotNil(saved)

        await store.clear(userId: 7)
        let cleared = try await store.load(userId: 7)
        XCTAssertNil(cleared)
    }

    private func makeStubbedSession() -> URLSession {
        let configuration = URLSessionConfiguration.ephemeral
        configuration.protocolClasses = [URLProtocolStub.self]
        return URLSession(configuration: configuration)
    }

    private static func bodyData(from request: URLRequest) -> Data? {
        if let body = request.httpBody {
            return body
        }
        guard let stream = request.httpBodyStream else {
            return nil
        }

        stream.open()
        defer { stream.close() }

        var data = Data()
        var buffer = [UInt8](repeating: 0, count: 1024)
        while stream.hasBytesAvailable {
            let count = stream.read(&buffer, maxLength: buffer.count)
            if count > 0 {
                data.append(buffer, count: count)
            } else {
                break
            }
        }
        return data
    }

    private static func assertAPIPath(
        _ url: URL?,
        _ expectedPath: String,
        file: StaticString = #filePath,
        line: UInt = #line
    ) {
        let components = url.flatMap { URLComponents(url: $0, resolvingAgainstBaseURL: false) }
        XCTAssertEqual(components?.path, expectedPath, file: file, line: line)
        let queryNames = Set((components?.queryItems ?? []).map(\.name))
        XCTAssertFalse(queryNames.contains("page"), file: file, line: line)
        XCTAssertFalse(queryNames.contains("action"), file: file, line: line)
    }

    private func cachedTicket(id: Int, title: String) -> TicketSummary {
        TicketSummary(
            id: id,
            hash: "hash-\(id)",
            code: "TK-\(id)",
            title: title,
            descriptionHtml: nil,
            descriptionText: nil,
            descriptionPreview: nil,
            status: TicketStatus(id: 1, name: "New", color: "#315BFF", group: "open", isClosed: false),
            priority: TicketPriority(id: 2, name: "Medium", color: "#64748B"),
            client: TicketClient(id: 3, name: "Aenze"),
            requester: TicketPerson(id: 4, name: "Eva Novak"),
            assignee: TicketPerson(id: 7, name: "Emma Carter"),
            source: "web",
            tags: ["vpn"],
            dueDate: nil,
            createdAt: "2026-07-06 10:00:00",
            updatedAt: "2026-07-06 10:30:00",
            url: nil,
            attachmentCount: 0,
            isArchived: false
        )
    }

    private func cachedTicketDetail(id: Int, title: String) -> TicketDetailPayload {
        TicketDetailPayload(
            ticket: cachedTicket(id: id, title: title),
            comments: [
                TicketComment(
                    id: 1001,
                    userId: 7,
                    authorName: "Emma Carter",
                    authorEmail: "emma@example.com",
                    contentHtml: "<p>Checked VPN profile.</p>",
                    contentText: "Checked VPN profile.",
                    isInternal: false,
                    createdAt: "2026-07-06 10:30:00"
                )
            ],
            attachments: [
                TicketAttachment(
                    id: 501,
                    ticketId: id,
                    commentId: 1001,
                    filename: "vpn.png",
                    mimeType: "image/png",
                    fileSize: 1024,
                    fileSizeLabel: "1 KB",
                    storageDriver: nil,
                    downloadUrl: "https://app.foxdesk.net/attachment.php?id=501",
                    previewUrl: "https://app.foxdesk.net/image.php?id=501",
                    canPreview: true,
                    createdAt: "2026-07-06 10:31:00"
                )
            ],
            timeEntries: [
                TicketTimeEntry(
                    id: 701,
                    commentId: nil,
                    userName: "Emma Carter",
                    startedAt: "2026-07-06 09:42:00",
                    endedAt: "2026-07-06 10:30:00",
                    durationMinutes: 48,
                    summary: "VPN profile check",
                    isBillable: true
                )
            ],
            actions: TicketActionsPayload(
                primary: [],
                statuses: [],
                priorities: [],
                assignees: [],
                timer: TicketTimerState(state: "idle", entryId: nil, elapsedMinutes: 0, elapsedLabel: "0 min")
            )
        )
    }

    private func cachedHomeFeed() -> HomeFeed {
        let ticket = HomeTicketCard(
            id: 42,
            hash: "abc",
            code: "TK-10042",
            title: "VPN access stopped working",
            descriptionPreview: "VPN rejects MFA codes",
            status: TicketStatus(id: 1, name: "Waiting", color: "#335CFF", group: "waiting", isClosed: false),
            priority: TicketPriority(id: 2, name: "High", color: "#FFAA00"),
            client: TicketClient(id: 3, name: "Aenze"),
            requester: "Eva Novak",
            assignee: "Emma Carter",
            source: "email",
            tags: ["vpn"],
            dueDate: nil,
            createdAt: "2026-07-06 09:00:00",
            updatedAt: "2026-07-06 10:00:00",
            url: nil
        )

        return HomeFeed(
            schemaVersion: 1,
            generatedAt: "2026-07-06T10:00:00+00:00",
            limit: 5,
            work: [
                "mine": HomeQueueSection(
                    definition: HomeQueueDefinition(
                        key: "mine",
                        title: "My work",
                        description: "Assigned to me",
                        icon: "inbox"
                    ),
                    count: 1,
                    items: [ticket]
                )
            ],
            inbox: [:],
            timers: [
                HomeTimer(
                    entryId: 77,
                    ticketId: 42,
                    ticketHash: "abc",
                    ticketTitle: "VPN access stopped working",
                    startedAt: "2026-07-06 09:35:00",
                    isPaused: false,
                    elapsedMinutes: 25,
                    elapsedLabel: "25 min",
                    url: nil
                )
            ],
            time: HomeTimeActivity(
                period: HomeTimePeriod(
                    key: "last_30_days",
                    label: "Last 30 days",
                    start: "2026-06-07 00:00:00",
                    end: "2026-07-06 23:59:59"
                ),
                totals: [
                    "today": HomeTimeTotal(minutes: 35, label: "35 min")
                ],
                entries: [
                    HomeTimeEntry(
                        id: 800,
                        ticketId: 42,
                        ticketHash: "abc",
                        ticketCode: "TK-10042",
                        ticketTitle: "VPN access stopped working",
                        clientName: "Aenze",
                        statusName: "Waiting",
                        summary: "Checked VPN profile.",
                        startedAt: "2026-07-06 09:35:00",
                        endedAt: "2026-07-06 10:00:00",
                        minutes: 25,
                        minutesLabel: "25 min",
                        url: nil
                    )
                ],
                team: nil,
                chart: HomeTimeChart(
                    days: [
                        HomeTimeChartDay(
                            key: "2026-07-06",
                            label: "06.07.",
                            fullLabel: "Monday, July 6",
                            minutes: 35,
                            minutesLabel: "35 min",
                            users: [
                                HomeTimeChartUser(userId: 7, name: "Emma Carter", minutes: 35, minutesLabel: "35 min")
                            ]
                        )
                    ],
                    maxMinutes: 35,
                    totalMinutes: 35,
                    totalLabel: "35 min"
                )
            ),
            notifications: HomeNotificationSummary(
                unreadCount: 3,
                items: [
                    AppNotificationItem(
                        id: 101,
                        type: "new_comment",
                        ticketId: 42,
                        isRead: false,
                        isResolved: false,
                        createdAt: "2026-07-05 09:40:00",
                        timeAgo: "2 min ago",
                        text: "New reply on VPN access stopped working",
                        actionText: "Open ticket",
                        snippet: "The VPN client now asks for MFA.",
                        isAction: true,
                        actor: NotificationActor(name: "Eva Novak", email: "eva@example.test", avatar: "uploads/eva.jpg")
                    )
                ]
            )
        )
    }
}

private final class URLProtocolStub: URLProtocol {
    static var requestHandler: ((URLRequest) throws -> (HTTPURLResponse, Data))?

    override class func canInit(with request: URLRequest) -> Bool {
        true
    }

    override class func canonicalRequest(for request: URLRequest) -> URLRequest {
        request
    }

    override func startLoading() {
        guard let handler = Self.requestHandler else {
            client?.urlProtocol(self, didFailWithError: URLError(.badServerResponse))
            return
        }

        do {
            let (response, data) = try handler(request)
            client?.urlProtocol(self, didReceive: response, cacheStoragePolicy: .notAllowed)
            client?.urlProtocol(self, didLoad: data)
            client?.urlProtocolDidFinishLoading(self)
        } catch {
            client?.urlProtocol(self, didFailWithError: error)
        }
    }

    override func stopLoading() {}
}
