import SwiftUI
import FoxDeskKit

struct DashboardView: View {
    @Environment(AppSession.self) private var session

    private let homeCache = HomeFeedCacheStore()

    @State private var home: HomeFeed?
    @State private var isLoading = false
    @State private var errorMessage: String?
    @State private var cacheMessage: String?
    @State private var createdTicketRoute: DashboardTicketRoute?
    @State private var isNewTicketPresented = false

    var body: some View {
        List {
            AccountSummarySection(
                userName: displayName,
                email: session.user?.email ?? "",
                avatarURL: session.client.resourceURL(from: session.user?.avatar)
            )

            if let cacheMessage {
                Section {
                    Label(cacheMessage, systemImage: "clock.arrow.circlepath")
                        .font(.subheadline)
                        .foregroundStyle(.secondary)
                }
            }

            if let errorMessage, home == nil {
                Section {
                    ContentUnavailableView(
                        "Could not load dashboard",
                        systemImage: "exclamationmark.triangle",
                        description: Text(errorMessage)
                    )
                }
            }

            if let home {
                ActiveTimersSection(timers: home.timers ?? [])

                if let time = home.time {
                    WorkedTimeSection(time: time)
                }

                WorkQueueSections(home: home)

                if let notifications = home.notifications?.items, !notifications.isEmpty {
                    RecentUpdatesSection(notifications: Array(notifications.prefix(3)))
                }

                QuickActionsSection(unreadCount: home.notifications?.unreadCount ?? 0)
            } else if isLoading {
                Section {
                    HStack {
                        ProgressView()
                        Text("Loading dashboard")
                            .foregroundStyle(.secondary)
                    }
                }
            }
        }
        .navigationTitle("Dashboard")
        .toolbar {
            ToolbarItem(placement: .topBarLeading) {
                Button {
                    isNewTicketPresented = true
                } label: {
                    Label("New ticket", systemImage: "plus")
                }
            }
            ToolbarItem(placement: .topBarTrailing) {
                Button {
                    Task { await loadHome() }
                } label: {
                    Image(systemName: "arrow.clockwise")
                }
                .disabled(isLoading)
            }
        }
        .navigationDestination(item: $createdTicketRoute) { route in
            TicketDetailView(ticketID: route.id)
        }
        .sheet(isPresented: $isNewTicketPresented) {
            NewTicketView { ticketID in
                createdTicketRoute = DashboardTicketRoute(id: ticketID)
                await loadHome()
            }
        }
        .task {
            await loadHome()
        }
        .refreshable {
            await loadHome()
        }
    }

    private var displayName: String {
        guard let name = session.user?.name, !name.isEmpty else {
            return "FoxDesk user"
        }
        return name
    }

    private func loadHome() async {
        isLoading = true
        errorMessage = nil
        if home == nil {
            await loadCachedHome(message: "Showing saved dashboard while FoxDesk refreshes.")
        }
        defer { isLoading = false }

        do {
            let freshHome = try await session.authenticated { accessToken in
                try await session.client.home(accessToken: accessToken, limit: 5)
            }.data.home
            home = freshHome
            cacheMessage = nil
            if let userId = session.user?.id {
                try? await homeCache.save(userId: userId, home: freshHome)
            }
        } catch {
            if home == nil {
                await loadCachedHome(message: "Showing saved dashboard. Pull to refresh when you are back online.")
            }
            if home == nil {
                errorMessage = error.localizedDescription
            } else {
                cacheMessage = "Showing saved dashboard. Pull to refresh when you are back online."
            }
        }
    }

    private func loadCachedHome(message: String) async {
        guard let userId = session.user?.id,
              let cached = try? await homeCache.load(userId: userId) else {
            return
        }
        home = cached.home
        cacheMessage = message
    }
}

private struct DashboardTicketRoute: Identifiable, Hashable {
    let id: Int
}
