import SwiftUI
import FoxDeskKit

struct WorkQueueSections: View {
    let home: HomeFeed

    private let preferredWorkOrder = ["mine", "unassigned", "overdue", "waiting", "done_today"]

    var body: some View {
        let queues = orderedQueues(home.work ?? [:], preferredOrder: preferredWorkOrder)

        if queues.isEmpty {
            Section("Work") {
                Text("No work queues available")
                    .foregroundStyle(.secondary)
            }
        } else {
            ForEach(queues, id: \.key) { item in
                QueueSectionView(key: item.key, section: item.section)
            }
        }
    }

    private func orderedQueues(
        _ queues: [String: HomeQueueSection],
        preferredOrder: [String]
    ) -> [(key: String, section: HomeQueueSection)] {
        let preferred = preferredOrder.compactMap { key -> (String, HomeQueueSection)? in
            guard let section = queues[key] else { return nil }
            return (key, section)
        }
        let remaining = queues
            .filter { !preferredOrder.contains($0.key) }
            .sorted { lhs, rhs in
                queueTitle(lhs.value, fallback: lhs.key) < queueTitle(rhs.value, fallback: rhs.key)
            }
        return preferred + remaining.map { ($0.key, $0.value) }
    }

    private func queueTitle(_ section: HomeQueueSection, fallback: String) -> String {
        section.definition?.title?.isEmpty == false ? section.definition?.title ?? fallback : fallback
    }
}

private struct QueueSectionView: View {
    let key: String
    let section: HomeQueueSection

    var body: some View {
        Section(title) {
            if let items = section.items, !items.isEmpty {
                ForEach(items) { ticket in
                    NavigationLink {
                        TicketDetailView(ticketID: ticket.id, ticketHash: ticket.hash)
                    } label: {
                        HomeTicketCardRow(ticket: ticket)
                    }
                }
            } else {
                Text("No tickets here")
                    .foregroundStyle(.secondary)
            }
        }
    }

    private var title: String {
        let label = section.definition?.title?.isEmpty == false ? section.definition?.title ?? key : key
        if let count = section.count {
            return "\(label) · \(count)"
        }
        return label
    }
}

private struct HomeTicketCardRow: View {
    let ticket: HomeTicketCard

    var body: some View {
        VStack(alignment: .leading, spacing: 8) {
            HStack(alignment: .firstTextBaseline) {
                Text(ticket.title)
                    .font(.headline)
                    .lineLimit(2)
                Spacer(minLength: 12)
                Text(ticket.code ?? "#\(ticket.id)")
                    .font(.caption)
                    .foregroundStyle(.secondary)
            }

            if let preview = ticket.descriptionPreview, !preview.isEmpty {
                Text(preview)
                    .font(.subheadline)
                    .foregroundStyle(.secondary)
                    .lineLimit(2)
            }

            HStack(spacing: 8) {
                TicketStatusBadge(status: ticket.status)
                if let client = ticket.client?.name, !client.isEmpty {
                    Label(client, systemImage: "building.2")
                        .font(.caption)
                        .foregroundStyle(.secondary)
                        .lineLimit(1)
                }
                Spacer()
            }
        }
        .padding(.vertical, 4)
    }
}
