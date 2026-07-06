import SwiftUI
import FoxDeskKit

struct TicketRow: View {
    let ticket: TicketSummary

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
                if (ticket.attachmentCount ?? 0) > 0 {
                    Label("\(ticket.attachmentCount ?? 0)", systemImage: "paperclip")
                        .font(.caption)
                        .foregroundStyle(.secondary)
                }
            }
        }
        .padding(.vertical, 4)
    }
}

struct TicketStatusBadge: View {
    let status: TicketStatus?

    var body: some View {
        HStack(spacing: 5) {
            Circle()
                .fill(statusColor)
                .frame(width: 8, height: 8)
            Text(status?.name?.isEmpty == false ? status?.name ?? "Status" : "Status")
        }
        .font(.caption.weight(.semibold))
        .padding(.horizontal, 9)
        .padding(.vertical, 4)
        .background(statusColor.opacity(0.12), in: Capsule())
        .foregroundStyle(statusColor)
    }

    private var statusColor: Color {
        Color(hex: status?.color) ?? .blue
    }
}

extension Color {
    init?(hex: String?) {
        guard var hex = hex?.trimmingCharacters(in: .whitespacesAndNewlines), !hex.isEmpty else {
            return nil
        }
        if hex.hasPrefix("#") {
            hex.removeFirst()
        }
        guard hex.count == 6, let value = Int(hex, radix: 16) else {
            return nil
        }
        let red = Double((value >> 16) & 0xFF) / 255.0
        let green = Double((value >> 8) & 0xFF) / 255.0
        let blue = Double(value & 0xFF) / 255.0
        self.init(red: red, green: green, blue: blue)
    }
}
