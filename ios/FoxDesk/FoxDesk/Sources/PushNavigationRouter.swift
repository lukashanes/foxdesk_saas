import Foundation
import Observation

extension Notification.Name {
    static let foxDeskOpenTicketFromNotification = Notification.Name("FoxDeskOpenTicketFromNotification")
}

enum PendingPushNavigationStore {
    private static let lock = NSLock()
    private static var ticketID: Int?

    static func store(ticketID: Int) {
        guard ticketID > 0 else { return }
        lock.withLock {
            self.ticketID = ticketID
        }
    }

    static func consumeTicketID() -> Int? {
        lock.withLock {
            let ticketID = self.ticketID
            self.ticketID = nil
            return ticketID
        }
    }

    static func clear() {
        lock.withLock {
            self.ticketID = nil
        }
    }
}

@MainActor
@Observable
final class PushNavigationRouter {
    private(set) var pendingTicketID: Int?
    private var observer: NSObjectProtocol?

    init(notificationCenter: NotificationCenter = .default) {
        observer = notificationCenter.addObserver(
            forName: .foxDeskOpenTicketFromNotification,
            object: nil,
            queue: .main
        ) { [weak self] notification in
            guard let ticketID = notification.object as? Int else { return }
            Task { @MainActor in
                self?.openTicket(id: PendingPushNavigationStore.consumeTicketID() ?? ticketID)
            }
        }

        if let ticketID = PendingPushNavigationStore.consumeTicketID() {
            openTicket(id: ticketID)
        }
    }

    func openTicket(id: Int) {
        guard id > 0 else { return }
        pendingTicketID = id
    }

    func consumePendingTicketID() -> Int? {
        let ticketID = pendingTicketID
        pendingTicketID = nil
        return ticketID
    }
}
