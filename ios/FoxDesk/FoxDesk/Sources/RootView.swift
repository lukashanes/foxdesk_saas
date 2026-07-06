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
        }
    }
}

struct SignedInShellView: View {
    @Environment(PushNavigationRouter.self) private var pushRouter

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
                            TicketDetailView(ticketID: route.ticketID)
                        }
                }
            }
            .tabItem {
                Label("Tickets", systemImage: "tray.full")
            }
            .tag(AppTab.tickets)

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
                WorkspaceAccessGate {
                    NotificationsView()
                }
            }
            .tabItem {
                Label("Notifications", systemImage: "bell")
            }
            .tag(AppTab.notifications)

            NavigationStack {
                SettingsView()
            }
            .tabItem {
                Label("Settings", systemImage: "gearshape")
            }
            .tag(AppTab.settings)
        }
        .task {
            openPendingTicketIfNeeded()
        }
        .onChange(of: pushRouter.pendingTicketID) { _, ticketID in
            guard ticketID != nil else { return }
            openPendingTicketIfNeeded()
        }
    }

    private func openPendingTicketIfNeeded() {
        guard let ticketID = pushRouter.consumePendingTicketID() else { return }
        selectedTab = .tickets
        ticketNotificationPath = [TicketNotificationRoute(ticketID: ticketID)]
    }
}

private enum AppTab: Hashable {
    case dashboard
    case tickets
    case search
    case notifications
    case settings
}

private struct TicketNotificationRoute: Hashable {
    let ticketID: Int
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
