import Foundation
import Observation
import SwiftUI
import UIKit
import UserNotifications
import FoxDeskKit

extension Notification.Name {
    static let foxDeskAPNsTokenReceived = Notification.Name("FoxDeskAPNsTokenReceived")
    static let foxDeskAPNsRegistrationFailed = Notification.Name("FoxDeskAPNsRegistrationFailed")
}

@MainActor
@Observable
final class PushRegistrationService {
    enum State: Equatable {
        case idle
        case requestingPermission
        case waitingForToken
        case registering
        case registered
        case denied
        case failed(String)
    }

    private(set) var state: State = .idle
    private(set) var apnsToken: String?
    private var tokenObserver: NSObjectProtocol?
    private var failureObserver: NSObjectProtocol?

    init(notificationCenter: NotificationCenter = .default) {
        tokenObserver = notificationCenter.addObserver(
            forName: .foxDeskAPNsTokenReceived,
            object: nil,
            queue: .main
        ) { [weak self] notification in
            guard let token = notification.object as? String else { return }
            Task { @MainActor in
                self?.apnsToken = token
                if self?.state == .waitingForToken {
                    self?.state = .idle
                }
            }
        }

        failureObserver = notificationCenter.addObserver(
            forName: .foxDeskAPNsRegistrationFailed,
            object: nil,
            queue: .main
        ) { [weak self] notification in
            let message = (notification.object as? Error)?.localizedDescription ?? "Could not register for push notifications."
            Task { @MainActor in
                self?.state = .failed(message)
            }
        }
    }

    func enableNotifications(session: AppSession) async {
        guard !isRegistrationInProgress else { return }
        do {
            state = .requestingPermission
            let granted = try await UNUserNotificationCenter.current().requestAuthorization(options: [.alert, .badge, .sound])
            guard granted else {
                state = .denied
                return
            }

            try await registerAuthorizedDevice(session: session)
        } catch {
            state = .failed(error.localizedDescription)
        }
    }

    func resumeAuthorizedRegistration(session: AppSession) async {
        // APNs registration is intentionally repeated when the app becomes
        // active. The backend endpoint is idempotent and this repairs a device
        // row that was removed, deactivated, or missed during an earlier
        // short-lived network failure without asking for permission again.
        guard !isRegistrationInProgress else { return }
        let settings = await UNUserNotificationCenter.current().notificationSettings()
        switch settings.authorizationStatus {
        case .authorized, .provisional, .ephemeral:
            do {
                try await registerAuthorizedDevice(session: session)
            } catch {
                state = .failed(error.localizedDescription)
            }
        case .denied:
            state = .denied
        case .notDetermined:
            state = .idle
        @unknown default:
            state = .idle
        }
    }

    var shouldOfferEnableAction: Bool {
        switch state {
        case .idle, .failed:
            return true
        default:
            return false
        }
    }

    func resetAfterSignOut() {
        state = .idle
        apnsToken = nil
    }

    private var currentAPNsEnvironment: APNsEnvironment {
        #if DEBUG
        return .sandbox
        #else
        return .production
        #endif
    }

    private var isRegistrationInProgress: Bool {
        switch state {
        case .requestingPermission, .waitingForToken, .registering:
            return true
        default:
            return false
        }
    }

    private func registerAuthorizedDevice(session: AppSession) async throws {
        state = .waitingForToken
        UIApplication.shared.registerForRemoteNotifications()

        let token = try await waitForAPNsToken()
        state = .registering
        _ = try await session.authenticated { accessToken in
            try await session.client.registerDevice(
                accessToken: accessToken,
                apnsToken: token,
                environment: currentAPNsEnvironment,
                device: session.device
            )
        }
        state = .registered
    }

    private func waitForAPNsToken() async throws -> String {
        if let apnsToken {
            return apnsToken
        }

        // APNs can take longer than a few seconds after install, entitlement
        // changes, or a network transition. Keep waiting long enough for a
        // physical device instead of treating a slow token as a broken API.
        for _ in 0..<120 {
            try await Task.sleep(for: .milliseconds(250))
            if let apnsToken {
                return apnsToken
            }
        }

        throw FoxDeskAPIError.invalidResponse
    }
}

final class FoxDeskAppDelegate: NSObject, UIApplicationDelegate, UNUserNotificationCenterDelegate {
    func application(
        _ application: UIApplication,
        didFinishLaunchingWithOptions launchOptions: [UIApplication.LaunchOptionsKey: Any]? = nil
    ) -> Bool {
        UNUserNotificationCenter.current().delegate = self
        if let userInfo = launchOptions?[.remoteNotification] as? [AnyHashable: Any] {
            queueTicketNavigation(from: userInfo)
        }
        return true
    }

    func application(
        _ application: UIApplication,
        didRegisterForRemoteNotificationsWithDeviceToken deviceToken: Data
    ) {
        let token = deviceToken.map { String(format: "%02.2hhx", $0) }.joined()
        NotificationCenter.default.post(name: .foxDeskAPNsTokenReceived, object: token)
    }

    func application(
        _ application: UIApplication,
        didFailToRegisterForRemoteNotificationsWithError error: Error
    ) {
        NotificationCenter.default.post(name: .foxDeskAPNsRegistrationFailed, object: error)
    }

    func userNotificationCenter(
        _ center: UNUserNotificationCenter,
        willPresent notification: UNNotification,
        withCompletionHandler completionHandler: @escaping (UNNotificationPresentationOptions) -> Void
    ) {
        completionHandler([.banner, .list, .sound])
    }

    func userNotificationCenter(
        _ center: UNUserNotificationCenter,
        didReceive response: UNNotificationResponse,
        withCompletionHandler completionHandler: @escaping () -> Void
    ) {
        let userInfo = response.notification.request.content.userInfo
        queueTicketNavigation(from: userInfo)
        completionHandler()
    }

    private func queueTicketNavigation(from userInfo: [AnyHashable: Any]) {
        guard let ticketID = FoxDeskNotificationPayload.ticketID(from: userInfo) else { return }
        let route = PushTicketRoute(
            id: ticketID,
            hash: FoxDeskNotificationPayload.ticketHash(from: userInfo)
        )
        PendingPushNavigationStore.store(ticketID: route.id, ticketHash: route.hash)
        NotificationCenter.default.post(name: .foxDeskOpenTicketFromNotification, object: route)
    }
}
