import SwiftUI
import FoxDeskKit

struct AccountSummarySection: View {
    let userName: String
    let email: String
    let avatarURL: URL?

    private var initials: String {
        let source = userName.isEmpty ? email : userName
        let value = source
            .split(separator: " ")
            .prefix(2)
            .compactMap(\.first)
            .map(String.init)
            .joined()
            .uppercased()
        if !value.isEmpty {
            return value
        }
        return email.first.map { String($0).uppercased() } ?? "FD"
    }

    var body: some View {
        Section {
            HStack(spacing: 12) {
                AccountAvatarView(avatarURL: avatarURL, initials: initials)

                VStack(alignment: .leading, spacing: 3) {
                    Text(userName)
                        .font(.headline)
                    if !email.isEmpty {
                        Text(email)
                            .font(.subheadline)
                            .foregroundStyle(.secondary)
                    }
                }
            }
            .padding(.vertical, 4)
        }
    }
}

private struct AccountAvatarView: View {
    let avatarURL: URL?
    let initials: String

    var body: some View {
        ZStack {
            Circle()
                .fill(Color.accentColor.opacity(0.16))

            Text(initials)
                .font(.headline.weight(.semibold))
                .foregroundStyle(Color.accentColor)

            if let avatarURL, avatarURL.scheme?.hasPrefix("http") == true {
                AsyncImage(url: avatarURL) { phase in
                    switch phase {
                    case .success(let image):
                        image
                            .resizable()
                            .scaledToFill()
                    default:
                        Color.clear
                    }
                }
                .clipShape(Circle())
            }
        }
        .frame(width: 44, height: 44)
        .accessibilityHidden(true)
    }
}

struct ActiveTimersSection: View {
    let timers: [HomeTimer]

    var body: some View {
        Section("In progress now") {
            if timers.isEmpty {
                Text("Nothing running right now")
                    .foregroundStyle(.secondary)
            } else {
                ForEach(timers) { timer in
                    NavigationLink {
                        TicketDetailView(ticketID: timer.ticketId, ticketHash: timer.ticketHash)
                    } label: {
                        HStack {
                            VStack(alignment: .leading, spacing: 4) {
                                Text(timer.ticketTitle.isEmpty ? "Ticket #\(timer.ticketId)" : timer.ticketTitle)
                                    .font(.headline)
                                    .lineLimit(2)
                                Text(timer.isPaused == true ? "Paused" : "Running")
                                    .font(.caption)
                                    .foregroundStyle(timer.isPaused == true ? .orange : .green)
                            }
                            Spacer()
                            Text(timer.elapsedLabel ?? "\(timer.elapsedMinutes) min")
                                .font(.headline)
                        }
                    }
                }
            }
        }
    }
}
