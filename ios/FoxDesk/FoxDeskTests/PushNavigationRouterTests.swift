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
        XCTAssertEqual(router.consumePendingTicket(), PushTicketRoute(id: 42, hash: nil))
        XCTAssertNil(router.pendingTicketID)
        XCTAssertNil(router.pendingTicketHash)
        XCTAssertNil(router.consumePendingTicket())
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

        notificationCenter.post(
            name: .foxDeskOpenTicketFromNotification,
            object: PushTicketRoute(id: 77, hash: "ticket-77")
        )
        try await Task.sleep(for: .milliseconds(50))

        XCTAssertEqual(router.pendingTicketID, 77)
        XCTAssertEqual(router.pendingTicketHash, "ticket-77")
        XCTAssertEqual(router.consumePendingTicket(), PushTicketRoute(id: 77, hash: "ticket-77"))
    }

    func testLaunchStorePendingTicketSurvivesBeforeRouterInit() {
        PendingPushNavigationStore.store(ticketID: 88, ticketHash: "ticket-88")

        let router = PushNavigationRouter(notificationCenter: NotificationCenter())

        XCTAssertEqual(router.pendingTicketID, 88)
        XCTAssertEqual(router.pendingTicketHash, "ticket-88")
        XCTAssertEqual(router.consumePendingTicket(), PushTicketRoute(id: 88, hash: "ticket-88"))
        XCTAssertNil(PendingPushNavigationStore.consumeTicket())
    }

    func testNotificationEventConsumesStoredLaunchTicket() async throws {
        let notificationCenter = NotificationCenter()
        let router = PushNavigationRouter(notificationCenter: notificationCenter)

        PendingPushNavigationStore.store(ticketID: 91, ticketHash: "ticket-91")
        notificationCenter.post(
            name: .foxDeskOpenTicketFromNotification,
            object: PushTicketRoute(id: 91, hash: "ticket-91")
        )
        try await Task.sleep(for: .milliseconds(50))

        XCTAssertEqual(router.pendingTicketID, 91)
        XCTAssertEqual(router.pendingTicketHash, "ticket-91")
        XCTAssertEqual(router.consumePendingTicket(), PushTicketRoute(id: 91, hash: "ticket-91"))
        XCTAssertNil(PendingPushNavigationStore.consumeTicket())
    }

    func testNotificationEventPrefersTappedTicketOverStaleStoredTicket() async throws {
        let notificationCenter = NotificationCenter()
        let router = PushNavigationRouter(notificationCenter: notificationCenter)

        PendingPushNavigationStore.store(ticketID: 91, ticketHash: "stale-ticket")
        notificationCenter.post(
            name: .foxDeskOpenTicketFromNotification,
            object: PushTicketRoute(id: 92, hash: "ticket-92")
        )
        try await Task.sleep(for: .milliseconds(50))

        XCTAssertEqual(router.pendingTicketID, 92)
        XCTAssertEqual(router.pendingTicketHash, "ticket-92")
        XCTAssertEqual(router.consumePendingTicket(), PushTicketRoute(id: 92, hash: "ticket-92"))
        XCTAssertNil(PendingPushNavigationStore.consumeTicket())
    }

    func testClearPendingNavigationDropsRuntimeAndLaunchTickets() {
        PendingPushNavigationStore.store(ticketID: 123)
        let router = PushNavigationRouter(notificationCenter: NotificationCenter())

        router.openTicket(id: 456)
        router.clearPendingNavigation()

        XCTAssertNil(router.pendingTicketID)
        XCTAssertNil(router.pendingTicketHash)
        XCTAssertNil(router.consumePendingTicket())
        XCTAssertNil(PendingPushNavigationStore.consumeTicket())
    }
}
