import Foundation
import Observation

extension Notification.Name {
    static let foxDeskOpenTicketFromNotification = Notification.Name("FoxDeskOpenTicketFromNotification")
}

enum PendingPushNavigationStore {
    private static let lock = NSLock()
    private static var ticket: PushTicketRoute?

    static func store(ticketID: Int, ticketHash: String? = nil) {
        guard ticketID > 0 else { return }
        lock.withLock {
            self.ticket = PushTicketRoute(id: ticketID, hash: ticketHash)
        }
    }

    static func consumeTicket() -> PushTicketRoute? {
        lock.withLock {
            let ticket = self.ticket
            self.ticket = nil
            return ticket
        }
    }

    static func clear() {
        lock.withLock {
            self.ticket = nil
        }
    }
}

struct PushTicketRoute: Equatable, Sendable {
    let id: Int
    let hash: String?
}

@MainActor
@Observable
final class PushNavigationRouter {
    private(set) var pendingTicketID: Int?
    private(set) var pendingTicketHash: String?
    private var observer: NSObjectProtocol?

    init(notificationCenter: NotificationCenter = .default) {
        observer = notificationCenter.addObserver(
            forName: .foxDeskOpenTicketFromNotification,
            object: nil,
            queue: .main
        ) { [weak self] notification in
            guard let route = notification.object as? PushTicketRoute else { return }
            Task { @MainActor in
                PendingPushNavigationStore.clear()
                self?.openTicket(id: route.id, hash: route.hash)
            }
        }

        if let route = PendingPushNavigationStore.consumeTicket() {
            openTicket(id: route.id, hash: route.hash)
        }
    }

    func openTicket(id: Int, hash: String? = nil) {
        guard id > 0 else { return }
        pendingTicketID = id
        pendingTicketHash = hash
    }

    func consumePendingTicket() -> PushTicketRoute? {
        guard let ticketID = pendingTicketID else { return nil }
        let route = PushTicketRoute(id: ticketID, hash: pendingTicketHash)
        pendingTicketID = nil
        pendingTicketHash = nil
        return route
    }

    func clearPendingNavigation() {
        pendingTicketID = nil
        pendingTicketHash = nil
        PendingPushNavigationStore.clear()
    }
}
