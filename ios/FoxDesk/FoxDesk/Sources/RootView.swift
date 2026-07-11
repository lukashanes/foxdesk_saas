import SwiftUI
import FoxDeskKit

struct RootView: View {
    @Environment(AppSession.self) private var session

    var body: some View {
        Group {
            switch session.state {
            case .signedIn:
                SignedInShellView()
            case .requiresTwoFactor(let challengeToken):
                TwoFactorView(challengeToken: challengeToken)
            case .signingIn:
                ProgressView("Signing in")
                    .frame(maxWidth: .infinity, maxHeight: .infinity)
            case .failed(let message):
                LoginView(errorMessage: message)
            case .signedOut:
                LoginView(errorMessage: nil)
            }
        }
        .task {
            await session.restore()

            #if DEBUG
            let environment = ProcessInfo.processInfo.environment
            if session.state == .signedOut,
               let email = environment["FOXDESK_AUTOMATION_EMAIL"],
               let password = environment["FOXDESK_AUTOMATION_PASSWORD"],
               !email.isEmpty,
               !password.isEmpty {
                await session.signIn(email: email, password: password)
            }
            #endif
        }
    }
}

struct SignedInShellView: View {
    @Environment(AppSession.self) private var session
    @Environment(PushRegistrationService.self) private var pushRegistration
    @Environment(PushNavigationRouter.self) private var pushRouter
    @Environment(\.scenePhase) private var scenePhase

    @State private var selectedTab: AppTab = .dashboard
    @State private var ticketNotificationPath: [TicketNotificationRoute] = []

    var body: some View {
        TabView(selection: $selectedTab) {
            NavigationStack {
                WorkspaceAccessGate {
                    DashboardView()
                }
            }
            .tabItem {
                Label("Dashboard", systemImage: "house")
            }
            .tag(AppTab.dashboard)

            NavigationStack(path: $ticketNotificationPath) {
                WorkspaceAccessGate {
                    TicketsView()
                        .navigationDestination(for: TicketNotificationRoute.self) { route in
                            TicketDetailView(ticketID: route.ticketID, ticketHash: route.ticketHash)
                        }
                }
            }
            .tabItem {
                Label("Tickets", systemImage: "tray.full")
            }
            .tag(AppTab.tickets)

            WorkspaceAccessGate {
                NewTicketView { ticketID in
                    openTicket(ticketID)
                }
            }
            .tabItem {
                Label("New ticket", systemImage: "plus.circle")
            }
            .tag(AppTab.newTicket)

            NavigationStack {
                WorkspaceAccessGate {
                    SearchView()
                }
            }
            .tabItem {
                Label("Search", systemImage: "magnifyingglass")
            }
            .tag(AppTab.search)

            NavigationStack {
                AccountView()
            }
            .tabItem {
                Label("Account", systemImage: "person.crop.circle")
            }
            .tag(AppTab.account)
        }
        .task {
            openPendingTicketIfNeeded()
            #if DEBUG
            if ProcessInfo.processInfo.environment["FOXDESK_AUTOMATION_ENABLE_NOTIFICATIONS"] == "1" {
                await pushRegistration.enableNotifications(session: session)
            } else {
                await pushRegistration.resumeAuthorizedRegistration(session: session)
            }
            #else
            await pushRegistration.resumeAuthorizedRegistration(session: session)
            #endif
        }
        .onChange(of: scenePhase) { _, phase in
            guard phase == .active else { return }
            Task {
                await pushRegistration.resumeAuthorizedRegistration(session: session)
            }
        }
        .onChange(of: pushRouter.pendingTicketID) { _, ticketID in
            guard ticketID != nil else { return }
            openPendingTicketIfNeeded()
        }
    }

    private func openPendingTicketIfNeeded() {
        guard let route = pushRouter.consumePendingTicket() else { return }
        selectedTab = .tickets
        ticketNotificationPath = [TicketNotificationRoute(ticketID: route.id, ticketHash: route.hash)]
    }

    @MainActor
    private func openTicket(_ ticketID: Int) {
        selectedTab = .tickets
        ticketNotificationPath = [TicketNotificationRoute(ticketID: ticketID, ticketHash: nil)]
    }
}

private enum AppTab: Hashable {
    case dashboard
    case tickets
    case newTicket
    case search
    case account
}

private struct TicketNotificationRoute: Hashable {
    let ticketID: Int
    let ticketHash: String?
}

private struct WorkspaceAccessGate<Content: View>: View {
    @Environment(AppSession.self) private var session

    let content: Content

    init(@ViewBuilder content: () -> Content) {
        self.content = content()
    }

    var body: some View {
        if session.workspaceAccessAllowed {
            content
                .task {
                    if session.tenantState == nil && !session.isLoadingTenantState {
                        await session.refreshTenantState()
                    }
                }
        } else {
            WorkspaceAccessBlockedView()
        }
    }
}

private struct WorkspaceAccessBlockedView: View {
    @Environment(AppSession.self) private var session

    var body: some View {
        List {
            Section {
                ContentUnavailableView(
                    title,
                    systemImage: "lock.circle",
                    description: Text(message)
                )

                if session.isLoadingTenantState {
                    HStack {
                        ProgressView()
                        Text("Checking workspace access")
                            .foregroundStyle(.secondary)
                    }
                }

                Button {
                    Task { await session.refreshTenantState() }
                } label: {
                    Label("Check again", systemImage: "arrow.clockwise")
                }
            }

            Section("What to do") {
                Text("Contact your workspace admin or FoxDesk support to restore access.")
                    .foregroundStyle(.secondary)
            }
        }
        .navigationTitle("Workspace access")
        .task {
            if session.tenantState == nil && !session.isLoadingTenantState {
                await session.refreshTenantState()
            }
        }
        .refreshable {
            await session.refreshTenantState()
        }
    }

    private var title: String {
        let notice = session.tenantState?.billingActions?.noticeTitle
        return notice?.isEmpty == false ? notice ?? "Workspace access is paused" : "Workspace access is paused"
    }

    private var message: String {
        let accessMessage = session.tenantState?.access.message
        if accessMessage?.isEmpty == false {
            return accessMessage ?? ""
        }
        let noticeBody = session.tenantState?.billingActions?.noticeBody
        if noticeBody?.isEmpty == false {
            return noticeBody ?? ""
        }
        return session.tenantStateError ?? "This workspace is not available right now."
    }
}
