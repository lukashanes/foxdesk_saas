import SwiftUI
import FoxDeskKit

struct RecentUpdatesSection: View {
    let notifications: [AppNotificationItem]

    var body: some View {
        Section("Recent updates") {
            ForEach(notifications) { notification in
                if let ticketID = notification.ticketId {
                    NavigationLink {
                        TicketDetailView(ticketID: ticketID)
                    } label: {
                        RecentUpdateRow(notification: notification)
                    }
                } else {
                    RecentUpdateRow(notification: notification)
                }
            }
        }
    }
}

private struct RecentUpdateRow: View {
    let notification: AppNotificationItem

    var body: some View {
        VStack(alignment: .leading, spacing: 5) {
            HStack(alignment: .firstTextBaseline, spacing: 8) {
                Text(notification.text?.isEmpty == false ? notification.text ?? "Ticket update" : "Ticket update")
                    .font(.subheadline.weight(notification.isRead ? .regular : .semibold))
                    .foregroundStyle(.primary)
                    .lineLimit(2)

                Spacer(minLength: 8)

                if let timeAgo = notification.timeAgo, !timeAgo.isEmpty {
                    Text(timeAgo)
                        .font(.caption2)
                        .foregroundStyle(.secondary)
                        .lineLimit(1)
                }
            }

            if let snippet = notification.snippet, !snippet.isEmpty {
                Text(snippet)
                    .font(.caption)
                    .foregroundStyle(.secondary)
                    .lineLimit(2)
            } else if let actionText = notification.actionText, !actionText.isEmpty {
                Text(actionText)
                    .font(.caption)
                    .foregroundStyle(.secondary)
                    .lineLimit(1)
            }
        }
        .padding(.vertical, 3)
    }
}
