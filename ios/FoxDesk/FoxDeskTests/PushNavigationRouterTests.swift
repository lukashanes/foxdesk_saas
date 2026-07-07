import XCTest
@testable import FoxDesk

@MainActor
final class PushNavigationRouterTests: XCTestCase {
    override func tearDown() {
        PendingPushNavigationStore.clear()
        super.tearDown()
    }

    func testOpenTicketStoresAndConsumesPendingTicket() {
        let router = PushNavigationRouter(notificationCenter: NotificationCenter())

        router.openTicket(id: 42)

        XCTAssertEqual(router.pendingTicketID, 42)
        XCTAssertEqual(router.consumePendingTicketID(), 42)
        XCTAssertNil(router.pendingTicketID)
        XCTAssertNil(router.consumePendingTicketID())
    }

    func testOpenTicketIgnoresInvalidIDs() {
        let router = PushNavigationRouter(notificationCenter: NotificationCenter())

        router.openTicket(id: 0)
        router.openTicket(id: -3)

        XCTAssertNil(router.pendingTicketID)
    }

    func testNotificationCenterEventStoresPendingTicket() async throws {
        let notificationCenter = NotificationCenter()
        let router = PushNavigationRouter(notificationCenter: notificationCenter)

        notificationCenter.post(name: .foxDeskOpenTicketFromNotification, object: 77)
        try await Task.sleep(for: .milliseconds(50))

        XCTAssertEqual(router.pendingTicketID, 77)
        XCTAssertEqual(router.consumePendingTicketID(), 77)
    }

    func testLaunchStorePendingTicketSurvivesBeforeRouterInit() {
        PendingPushNavigationStore.store(ticketID: 88)

        let router = PushNavigationRouter(notificationCenter: NotificationCenter())

        XCTAssertEqual(router.pendingTicketID, 88)
        XCTAssertEqual(router.consumePendingTicketID(), 88)
        XCTAssertNil(PendingPushNavigationStore.consumeTicketID())
    }

    func testNotificationEventConsumesStoredLaunchTicket() async throws {
        let notificationCenter = NotificationCenter()
        let router = PushNavigationRouter(notificationCenter: notificationCenter)

        PendingPushNavigationStore.store(ticketID: 91)
        notificationCenter.post(name: .foxDeskOpenTicketFromNotification, object: 91)
        try await Task.sleep(for: .milliseconds(50))

        XCTAssertEqual(router.pendingTicketID, 91)
        XCTAssertEqual(router.consumePendingTicketID(), 91)
        XCTAssertNil(PendingPushNavigationStore.consumeTicketID())
    }

    func testNotificationEventPrefersTappedTicketOverStaleStoredTicket() async throws {
        let notificationCenter = NotificationCenter()
        let router = PushNavigationRouter(notificationCenter: notificationCenter)

        PendingPushNavigationStore.store(ticketID: 91)
        notificationCenter.post(name: .foxDeskOpenTicketFromNotification, object: 92)
        try await Task.sleep(for: .milliseconds(50))

        XCTAssertEqual(router.pendingTicketID, 92)
        XCTAssertEqual(router.consumePendingTicketID(), 92)
        XCTAssertNil(PendingPushNavigationStore.consumeTicketID())
    }

    func testClearPendingNavigationDropsRuntimeAndLaunchTickets() {
        PendingPushNavigationStore.store(ticketID: 123)
        let router = PushNavigationRouter(notificationCenter: NotificationCenter())

        router.openTicket(id: 456)
        router.clearPendingNavigation()

        XCTAssertNil(router.pendingTicketID)
        XCTAssertNil(router.consumePendingTicketID())
        XCTAssertNil(PendingPushNavigationStore.consumeTicketID())
    }
}
