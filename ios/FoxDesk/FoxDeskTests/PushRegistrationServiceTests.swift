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
}
