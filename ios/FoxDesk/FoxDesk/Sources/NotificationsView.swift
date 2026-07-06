import SwiftUI
import FoxDeskKit

struct NotificationsView: View {
    @Environment(AppSession.self) private var session

    @State private var notifications: [AppNotificationItem] = []
    @State private var unreadCount = 0
    @State private var totalCount: Int?
    @State private var hasMoreNotifications = false
    @State private var selectedTicket: NotificationTicketRoute?
    @State private var isLoading = false
    @State private var isLoadingMore = false
    @State private var errorMessage: String?
    @State private var loadMoreError: String?

    private let pageSize = 25

    var body: some View {
        List {
            if let errorMessage {
                Section {
                    ContentUnavailableView(
                        "Could not load notifications",
                        systemImage: "exclamationmark.triangle",
                        description: Text(errorMessage)
                    )
                }
            } else if isLoading && notifications.isEmpty {
                Section {
                    HStack {
                        ProgressView()
                        Text("Loading notifications")
                            .foregroundStyle(.secondary)
                    }
                }
            } else if notifications.isEmpty {
                Section {
                    ContentUnavailableView(
                        "No notifications",
                        systemImage: "bell",
                        description: Text("Ticket updates will show up here.")
                    )
                }
            } else {
                Section(unreadLabel) {
                    ForEach(notifications) { notification in
                        Button {
                            open(notification)
                        } label: {
                            NotificationRow(notification: notification)
                        }
                        .buttonStyle(.plain)
                    }

                    if hasMoreNotifications {
                        loadMoreRow
                    }
                }
            }
        }
        .navigationTitle("Notifications")
        .toolbar {
            ToolbarItem(placement: .topBarLeading) {
                Button("Mark all read") {
                    Task { await markAllRead() }
                }
                .disabled(isLoading || unreadCount == 0)
            }
            ToolbarItem(placement: .topBarTrailing) {
                Button {
                    Task { await loadNotifications(reset: true) }
                } label: {
                    Image(systemName: "arrow.clockwise")
                }
                .disabled(isLoading)
            }
        }
        .navigationDestination(item: $selectedTicket) { route in
            TicketDetailView(ticketID: route.id)
                .task {
                    await markTicketNotificationsRead(ticketID: route.id)
                }
        }
        .task {
            await loadNotifications(reset: true)
        }
        .refreshable {
            await loadNotifications(reset: true)
        }
    }

    private var unreadLabel: String {
        if unreadCount > 0 {
            return unreadCount == 1 ? "1 unread" : "\(unreadCount) unread"
        }
        if let totalCount {
            return "\(totalCount) notifications"
        }
        return "Notifications"
    }

    @ViewBuilder
    private var loadMoreRow: some View {
        if isLoadingMore {
            HStack {
                ProgressView()
                Text("Loading more notifications")
                    .foregroundStyle(.secondary)
            }
        } else {
            VStack(alignment: .leading, spacing: 6) {
                Button {
                    Task { await loadMoreNotifications() }
                } label: {
                    Label("Load more notifications", systemImage: "arrow.down.circle")
                }

                if let loadMoreError {
                    Text(loadMoreError)
                        .font(.caption)
                        .foregroundStyle(.orange)
                }
            }
        }
    }

    private func open(_ notification: AppNotificationItem) {
        if let ticketID = notification.ticketId {
            selectedTicket = NotificationTicketRoute(id: ticketID)
        }

        if !notification.isRead {
            Task { await markRead(notificationID: notification.id) }
        }
    }

    private func loadNotifications(reset: Bool) async {
        if reset {
            notifications = []
            totalCount = nil
            hasMoreNotifications = false
            loadMoreError = nil
        }

        isLoading = true
        errorMessage = nil
        defer { isLoading = false }

        do {
            let response = try await fetchNotificationsPage(offset: 0)
            notifications = response.data.items
            unreadCount = response.data.unreadCount
            totalCount = response.data.pagination?.total
            hasMoreNotifications = response.data.pagination?.hasMore ?? hasMoreFromTotal
        } catch {
            errorMessage = error.localizedDescription
        }
    }

    private func loadMoreNotifications() async {
        guard hasMoreNotifications, !isLoadingMore else { return }
        isLoadingMore = true
        loadMoreError = nil
        defer { isLoadingMore = false }

        do {
            let response = try await fetchNotificationsPage(offset: notifications.count)
            appendUniqueNotifications(response.data.items)
            unreadCount = response.data.unreadCount
            totalCount = response.data.pagination?.total ?? totalCount
            hasMoreNotifications = response.data.pagination?.hasMore ?? hasMoreFromTotal
        } catch {
            loadMoreError = error.localizedDescription
        }
    }

    private func fetchNotificationsPage(offset: Int) async throws -> AppEnvelope<NotificationsPayload> {
        try await session.authenticated { accessToken in
            try await session.client.notifications(
                accessToken: accessToken,
                limit: pageSize,
                offset: offset
            )
        }
    }

    private var hasMoreFromTotal: Bool {
        guard let totalCount else {
            return false
        }
        return notifications.count < totalCount
    }

    private func appendUniqueNotifications(_ newNotifications: [AppNotificationItem]) {
        var seenIds = Set(notifications.map(\.id))
        let uniqueNotifications = newNotifications.filter { notification in
            if seenIds.contains(notification.id) {
                return false
            }
            seenIds.insert(notification.id)
            return true
        }
        notifications.append(contentsOf: uniqueNotifications)
    }

    private func markRead(notificationID: Int) async {
        do {
            _ = try await session.authenticated { accessToken in
                try await session.client.setNotificationReadState(
                    accessToken: accessToken,
                    request: NotificationReadStateRequest(
                        scope: "notification",
                        notificationId: notificationID,
                        isRead: true
                    )
                )
            }
            await loadNotifications(reset: true)
        } catch {
            errorMessage = error.localizedDescription
        }
    }

    private func markTicketNotificationsRead(ticketID: Int) async {
        do {
            _ = try await session.authenticated { accessToken in
                try await session.client.setNotificationReadState(
                    accessToken: accessToken,
                    request: NotificationReadStateRequest(scope: "ticket", ticketId: ticketID)
                )
            }
            await loadNotifications(reset: true)
        } catch {
            errorMessage = error.localizedDescription
        }
    }

    private func markAllRead() async {
        isLoading = true
        errorMessage = nil
        defer { isLoading = false }

        do {
            _ = try await session.authenticated { accessToken in
                try await session.client.setNotificationReadState(
                    accessToken: accessToken,
                    request: NotificationReadStateRequest(scope: "all")
                )
            }
            await loadNotifications(reset: true)
        } catch {
            errorMessage = error.localizedDescription
        }
    }
}

private struct NotificationTicketRoute: Identifiable, Hashable {
    let id: Int
}

private struct NotificationRow: View {
    let notification: AppNotificationItem

    var body: some View {
        HStack(alignment: .top, spacing: 12) {
            Circle()
                .fill(notification.isRead ? Color.secondary.opacity(0.24) : Color.accentColor)
                .frame(width: 10, height: 10)
                .padding(.top, 6)

            VStack(alignment: .leading, spacing: 6) {
                HStack(alignment: .firstTextBaseline) {
                    Text(rowTitle)
                        .font(.subheadline.weight(notification.isRead ? .regular : .semibold))
                        .foregroundStyle(.primary)
                        .lineLimit(2)

                    Spacer(minLength: 8)

                    if let timeAgo = notification.timeAgo, !timeAgo.isEmpty {
                        Text(timeAgo)
                            .font(.caption)
                            .foregroundStyle(.secondary)
                            .lineLimit(1)
                    }
                }

                if let snippet = notification.snippet, !snippet.isEmpty {
                    Text(snippet)
                        .font(.caption)
                        .foregroundStyle(.secondary)
                        .lineLimit(3)
                }

                HStack(spacing: 8) {
                    if let actor = notification.actor?.name, !actor.isEmpty {
                        Label(actor, systemImage: "person")
                    }
                    if notification.ticketId != nil {
                        Label("Open ticket", systemImage: "arrow.right")
                    }
                }
                .font(.caption2)
                .foregroundStyle(.secondary)
            }
        }
        .padding(.vertical, 4)
    }

    private var rowTitle: String {
        if let action = notification.actionText, !action.isEmpty {
            return action
        }
        if let text = notification.text, !text.isEmpty {
            return text
        }
        return notification.type.replacingOccurrences(of: "_", with: " ").capitalized
    }
}
