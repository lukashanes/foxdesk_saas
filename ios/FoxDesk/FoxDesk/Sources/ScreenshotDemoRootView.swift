#if DEBUG
import SwiftUI

enum ScreenshotDemoConfiguration {
    static var isEnabled: Bool {
        ProcessInfo.processInfo.arguments.contains("--foxdesk-screenshot-mode")
    }

    static var screen: ScreenshotDemoScreen {
        let arguments = ProcessInfo.processInfo.arguments
        guard let index = arguments.firstIndex(of: "--foxdesk-screenshot-screen"),
              arguments.indices.contains(index + 1) else {
            return .dashboard
        }
        let requestedScreen = arguments[index + 1]
        if requestedScreen == "settings" {
            return .account
        }
        return ScreenshotDemoScreen(rawValue: requestedScreen) ?? .dashboard
    }
}

enum ScreenshotDemoScreen: String, CaseIterable {
    case signin
    case dashboard
    case tickets
    case ticketDetail = "ticket-detail"
    case reply
    case attachment
    case search
    case client
    case notifications
    case account
}

struct ScreenshotDemoRootView: View {
    private let screen = ScreenshotDemoConfiguration.screen

    var body: some View {
        NavigationStack {
            switch screen {
            case .signin:
                ScreenshotSignInView()
            case .dashboard:
                ScreenshotDashboardView()
            case .tickets:
                ScreenshotTicketsView()
            case .ticketDetail:
                ScreenshotTicketDetailView(mode: .detail)
            case .reply:
                ScreenshotTicketDetailView(mode: .reply)
            case .attachment:
                ScreenshotAttachmentView()
            case .search:
                ScreenshotSearchView()
            case .client:
                ScreenshotClientView()
            case .notifications:
                ScreenshotNotificationsView()
            case .account:
                ScreenshotAccountView()
            }
        }
    }
}

private struct ScreenshotSignInView: View {
    var body: some View {
        VStack(alignment: .leading, spacing: 24) {
            Spacer(minLength: 32)
            Image(systemName: "bubble.left.and.bubble.right.fill")
                .font(.system(size: 48, weight: .semibold))
                .foregroundStyle(.blue)
            Text("FoxDesk")
                .font(.largeTitle.bold())
            Text("Manage tickets, replies, time, and attachments from your iPhone.")
                .font(.title3)
                .foregroundStyle(.secondary)
            VStack(spacing: 14) {
                demoField("agent@example.com", systemImage: "person")
                demoField("Password", systemImage: "lock", secure: true)
            }
            Button {
            } label: {
                Text("Sign in")
                    .frame(maxWidth: .infinity)
            }
            .buttonStyle(.borderedProminent)
            .controlSize(.large)
            Spacer()
        }
        .padding(24)
        .navigationTitle("Sign in")
    }

    private func demoField(_ text: String, systemImage: String, secure: Bool = false) -> some View {
        HStack {
            Image(systemName: systemImage)
                .foregroundStyle(.secondary)
            Text(secure ? "••••••••••" : text)
                .foregroundStyle(secure ? .secondary : .primary)
            Spacer()
        }
        .padding(14)
        .background(.thinMaterial, in: RoundedRectangle(cornerRadius: 12))
    }
}

private struct ScreenshotDashboardView: View {
    var body: some View {
        List {
            Section {
                HStack(spacing: 14) {
                    Circle()
                        .fill(.blue.gradient)
                        .frame(width: 52, height: 52)
                        .overlay(Text("EC").font(.headline).foregroundStyle(.white))
                    VStack(alignment: .leading) {
                        Text("Emma Carter")
                            .font(.headline)
                        Text("Aenze Support")
                            .foregroundStyle(.secondary)
                    }
                }
            }

            Section("Worked time") {
                HStack(spacing: 12) {
                    metric("Today", "2h 15m")
                    metric("This week", "9h 40m")
                    metric("This month", "34h 20m")
                }
                DemoBarChart()
                    .frame(height: 160)
            }

            Section("In progress now") {
                demoTicket("Website launch checklist", code: "TK-10842", meta: "Running · 42 min", color: .green)
                demoTicket("Invoice email formatting", code: "TK-10839", meta: "Waiting for customer", color: .orange)
            }

            Section("Recent work") {
                demoTicket("Product photos for summer campaign", code: "TK-10833", meta: "Reply added · 18 min", color: .blue)
                demoTicket("VPN access stopped working", code: "TK-10821", meta: "Internal note · 12 min", color: .purple)
            }
        }
        .navigationTitle("Dashboard")
    }
}

private struct ScreenshotTicketsView: View {
    var body: some View {
        List {
            Section {
                Picker("Ticket view", selection: .constant("Mine")) {
                    Text("Mine").tag("Mine")
                    Text("New").tag("New")
                    Text("Waiting").tag("Waiting")
                    Text("Done").tag("Done")
                }
                .pickerStyle(.segmented)
            }

            Section("12 tickets") {
                demoTicket("VPN access stopped working", code: "TK-10821", meta: "Aenze · Urgent · 10 min ago", color: .red)
                demoTicket("Prepare onboarding checklist", code: "TK-10819", meta: "Northline · In progress", color: .blue)
                demoTicket("Newsletter image is cropped", code: "TK-10817", meta: "EnviTrail · Waiting", color: .orange)
                demoTicket("Update billing report comments", code: "TK-10810", meta: "Studio Care · Done today", color: .green)
            }
        }
        .searchable(text: .constant(""), prompt: "Search tickets")
        .navigationTitle("Tickets")
    }
}

private struct ScreenshotTicketDetailView: View {
    enum Mode {
        case detail
        case reply
    }

    let mode: Mode

    var body: some View {
        List {
            Section {
                VStack(alignment: .leading, spacing: 10) {
                    Text("VPN access stopped working")
                        .font(.title2.bold())
                    Text("TK-10821 · Aenze · Urgent")
                        .foregroundStyle(.secondary)
                    Text("The VPN client asks for MFA on every connection and rejects the code after the first attempt.")
                }
            }

            Section("Activity") {
                comment("Eva Novak", "The issue started after the latest client update.", time: "09:42")
                comment("Emma Carter", "I reproduced the MFA loop and attached a screenshot.", time: "10:08")
                timeEntry("Emma Carter", "Diagnostics and account policy review", duration: "32 min")
            }

            if mode == .reply {
                Section("Reply") {
                    Text("Hi Eva,\n\nwe found the VPN client policy conflict and are rolling back the affected rule now.")
                        .frame(maxWidth: .infinity, alignment: .leading)
                        .padding(.vertical, 8)
                    Toggle("Internal note", isOn: .constant(false))
                    LabeledContent("Time", value: "18 min")
                    Button("Send update") {}
                        .buttonStyle(.borderedProminent)
                }
            }
        }
        .navigationTitle(mode == .reply ? "Reply" : "Ticket")
    }
}

private struct ScreenshotAttachmentView: View {
    var body: some View {
        List {
            Section("Attachment preview") {
                RoundedRectangle(cornerRadius: 18)
                    .fill(LinearGradient(
                        colors: [.blue.opacity(0.85), .cyan.opacity(0.65)],
                        startPoint: .topLeading,
                        endPoint: .bottomTrailing
                    ))
                    .frame(height: 260)
                    .overlay {
                        VStack(spacing: 12) {
                            Image(systemName: "photo")
                                .font(.system(size: 58))
                            Text("vpn-error-screenshot.png")
                                .font(.headline)
                        }
                        .foregroundStyle(.white)
                    }
                Label("Image preview is authorized by FoxDesk before download.", systemImage: "lock.shield")
            }

            Section("Files") {
                Label("vpn-error-screenshot.png · 480 KB", systemImage: "photo")
                Label("diagnostics-log.txt · 24 KB", systemImage: "doc.text")
            }
        }
        .navigationTitle("Attachment")
    }
}

private struct ScreenshotSearchView: View {
    var body: some View {
        List {
            Section("Tickets") {
                demoTicket("VPN access stopped working", code: "TK-10821", meta: "Open · Aenze", color: .red)
                demoTicket("VPN setup for new laptop", code: "TK-10798", meta: "Done · Aenze", color: .green)
            }
            Section("Clients") {
                Label("Aenze", systemImage: "building.2")
                Label("Northline Support", systemImage: "building.2")
            }
            Section("Contacts") {
                Label("Eva Novak · eva@example.com", systemImage: "person")
            }
        }
        .searchable(text: .constant("vpn"), prompt: "Search FoxDesk")
        .navigationTitle("Search")
    }
}

private struct ScreenshotClientView: View {
    var body: some View {
        List {
            Section {
                Text("Aenze")
                    .font(.title2.bold())
                Text("4 open tickets · 34h this month")
                    .foregroundStyle(.secondary)
            }
            Section("Open tickets") {
                demoTicket("VPN access stopped working", code: "TK-10821", meta: "Urgent", color: .red)
                demoTicket("Billing report comments", code: "TK-10810", meta: "Waiting", color: .orange)
            }
            Section("Contacts") {
                Label("Eva Novak", systemImage: "person.crop.circle")
                Label("Lukas Hanes", systemImage: "person.crop.circle")
            }
        }
        .navigationTitle("Client")
    }
}

private struct ScreenshotNotificationsView: View {
    var body: some View {
        List {
            Section("Today") {
                notification("New reply", "Eva replied to VPN access stopped working", unread: true)
                notification("Assigned to you", "Prepare onboarding checklist", unread: true)
            }
            Section("Yesterday") {
                notification("Ticket done", "Newsletter image is cropped", unread: false)
                notification("Mention", "Emma mentioned you in billing report comments", unread: false)
            }
        }
        .navigationTitle("Notifications")
    }
}

private struct ScreenshotAccountView: View {
    var body: some View {
        List {
            Section("Workspace") {
                LabeledContent("Name", value: "Aenze Support")
                LabeledContent("Status", value: "Active")
            }
            Section("Notifications") {
                LabeledContent("Status", value: "Enabled")
                Label("Push notifications are enabled for ticket updates.", systemImage: "bell.badge")
            }
            Section("Account") {
                LabeledContent("Name", value: "Emma Carter")
                LabeledContent("Email", value: "emma@example.com")
                LabeledContent("Role", value: "Admin")
            }
            Section("Help and legal") {
                Label("Contact support", systemImage: "questionmark.circle")
                Label("Privacy Policy", systemImage: "hand.raised")
                Label("Terms", systemImage: "doc.text")
            }
            Section("Data requests") {
                Label("Request account deletion", systemImage: "person.crop.circle.badge.xmark")
            }
        }
        .navigationTitle("Account")
    }
}

private struct DemoBarChart: View {
    private let values: [Double] = [0.2, 0.35, 0.25, 0.55, 0.45, 0.7, 0.5, 0.82, 0.62, 0.4, 0.28, 0.58]

    var body: some View {
        HStack(alignment: .bottom, spacing: 8) {
            ForEach(Array(values.enumerated()), id: \.offset) { index, value in
                RoundedRectangle(cornerRadius: 5)
                    .fill(barColor(index).gradient)
                    .frame(maxWidth: .infinity)
                    .frame(height: max(14, 130 * value))
            }
        }
        .padding(.vertical, 12)
    }

    private func barColor(_ index: Int) -> Color {
        index.isMultiple(of: 3) ? .green : .blue
    }
}

private func metric(_ title: String, _ value: String) -> some View {
    VStack(alignment: .leading, spacing: 4) {
        Text(title.uppercased())
            .font(.caption.bold())
            .foregroundStyle(.secondary)
        Text(value)
            .font(.headline)
    }
    .frame(maxWidth: .infinity, alignment: .leading)
    .padding(12)
    .background(.thinMaterial, in: RoundedRectangle(cornerRadius: 14))
}

private func demoTicket(_ title: String, code: String, meta: String, color: Color) -> some View {
    HStack(spacing: 12) {
        Circle()
            .fill(color)
            .frame(width: 10, height: 10)
        VStack(alignment: .leading, spacing: 4) {
            Text(title)
                .font(.headline)
            Text("\(code) · \(meta)")
                .font(.subheadline)
                .foregroundStyle(.secondary)
        }
        Spacer()
        Image(systemName: "chevron.right")
            .font(.caption)
            .foregroundStyle(.tertiary)
    }
}

private func comment(_ author: String, _ text: String, time: String) -> some View {
    VStack(alignment: .leading, spacing: 6) {
        HStack {
            Text(author)
                .font(.headline)
            Spacer()
            Text(time)
                .font(.caption)
                .foregroundStyle(.secondary)
        }
        Text(text)
            .foregroundStyle(.secondary)
    }
}

private func timeEntry(_ author: String, _ text: String, duration: String) -> some View {
    Label {
        VStack(alignment: .leading, spacing: 3) {
            Text(text)
            Text("\(author) · \(duration)")
                .font(.caption)
                .foregroundStyle(.secondary)
        }
    } icon: {
        Image(systemName: "clock")
    }
}

private func notification(_ title: String, _ body: String, unread: Bool) -> some View {
    HStack(spacing: 12) {
        Circle()
            .fill(unread ? .blue : .gray.opacity(0.25))
            .frame(width: 10, height: 10)
        VStack(alignment: .leading, spacing: 4) {
            Text(title)
                .font(.headline)
            Text(body)
                .font(.subheadline)
                .foregroundStyle(.secondary)
        }
    }
}
#endif
