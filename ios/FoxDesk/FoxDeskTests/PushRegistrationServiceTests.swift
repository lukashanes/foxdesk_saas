import XCTest
@testable import FoxDesk

@MainActor
final class PushRegistrationServiceTests: XCTestCase {
    func testAPNsTokenNotificationStoresTokenForDiagnostics() async throws {
        let notificationCenter = NotificationCenter()
        let service = PushRegistrationService(notificationCenter: notificationCenter)

        notificationCenter.post(name: .foxDeskAPNsTokenReceived, object: String(repeating: "a", count: 64))
        try await Task.sleep(for: .milliseconds(50))

        XCTAssertEqual(service.apnsToken, String(repeating: "a", count: 64))
    }

    func testAPNsRegistrationFailureUpdatesState() async throws {
        let notificationCenter = NotificationCenter()
        let service = PushRegistrationService(notificationCenter: notificationCenter)
        let error = NSError(domain: "FoxDeskPushTests", code: 7, userInfo: [
            NSLocalizedDescriptionKey: "Notifications are unavailable"
        ])

        notificationCenter.post(name: .foxDeskAPNsRegistrationFailed, object: error)
        try await Task.sleep(for: .milliseconds(50))

        XCTAssertEqual(service.state, .failed("Notifications are unavailable"))
    }

    func testResetAfterSignOutClearsLocalPushState() async throws {
        let notificationCenter = NotificationCenter()
        let service = PushRegistrationService(notificationCenter: notificationCenter)
        let token = String(repeating: "b", count: 64)
        let error = NSError(domain: "FoxDeskPushTests", code: 9, userInfo: [
            NSLocalizedDescriptionKey: "Temporary push failure"
        ])

        notificationCenter.post(name: .foxDeskAPNsTokenReceived, object: token)
        notificationCenter.post(name: .foxDeskAPNsRegistrationFailed, object: error)
        try await Task.sleep(for: .milliseconds(50))

        XCTAssertEqual(service.apnsToken, token)
        XCTAssertEqual(service.state, .failed("Temporary push failure"))

        service.resetAfterSignOut()

        XCTAssertNil(service.apnsToken)
        XCTAssertEqual(service.state, .idle)
    }

    func testIdlePushStateOffersNotificationEnableAction() {
        let service = PushRegistrationService(notificationCenter: NotificationCenter())

        XCTAssertTrue(service.shouldOfferEnableAction)
    }

    func testReceivedTokenRemainsAvailableForForegroundResynchronization() async throws {
        let notificationCenter = NotificationCenter()
        let service = PushRegistrationService(notificationCenter: notificationCenter)
        let token = String(repeating: "c", count: 64)

        notificationCenter.post(name: .foxDeskAPNsTokenReceived, object: token)
        try await Task.sleep(for: .milliseconds(50))

        XCTAssertEqual(service.apnsToken, token)
        XCTAssertEqual(service.state, .idle)
        XCTAssertTrue(service.shouldOfferEnableAction)
    }
}
