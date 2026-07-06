import SwiftUI
import FoxDeskKit

struct ClientContextView: View {
    @Environment(AppSession.self) private var session

    let organizationID: Int
    let fallbackName: String

    @State private var overview: ClientOverviewPayload?
    @State private var selectedView = "open"
    @State private var isLoading = false
    @State private var errorMessage: String?

    private let viewOptions: [(id: String, title: String)] = [
        ("open", "Open"),
        ("waiting", "Waiting"),
        ("done", "Done"),
        ("all", "All")
    ]

    var body: some View {
        List {
            if let errorMessage {
                Section {
                    ContentUnavailableView(
                        "Could not load client",
                        systemImage: "exclamationmark.triangle",
                        description: Text(errorMessage)
                    )
                }
            }

            if let overview {
                ClientSummarySection(overview: overview)

                Section("Tickets") {
                    Picker("Ticket view", selection: $selectedView) {
                        ForEach(viewOptions, id: \.id) { option in
                            Text(option.title).tag(option.id)
                        }
                    }
                    .pickerStyle(.segmented)

                    if overview.tickets.isEmpty {
                        Text("No tickets in this view")
                            .foregroundStyle(.secondary)
                    } else {
                        ForEach(overview.tickets) { ticket in
                            NavigationLink {
                                TicketDetailView(ticketID: ticket.id)
                            } label: {
                                ClientTicketRow(ticket: ticket)
                            }
                        }
                    }
                }

                ClientContactsSection(contacts: overview.contacts)
            } else if isLoading {
                Section {
                    HStack {
                        ProgressView()
                        Text("Loading client")
                            .foregroundStyle(.secondary)
                    }
                }
            }
        }
        .navigationTitle(overview?.client.name.isEmpty == false ? overview?.client.name ?? fallbackName : fallbackName)
        .navigationBarTitleDisplayMode(.inline)
        .toolbar {
            ToolbarItem(placement: .topBarTrailing) {
                Button {
                    Task { await loadOverview() }
                } label: {
                    Image(systemName: "arrow.clockwise")
                }
                .disabled(isLoading)
            }
        }
        .task(id: selectedView) {
            await loadOverview()
        }
        .refreshable {
            await loadOverview()
        }
    }

    private func loadOverview() async {
        isLoading = true
        errorMessage = nil
        defer { isLoading = false }

        do {
            overview = try await session.authenticated { accessToken in
                try await session.client.clientOverview(
                    accessToken: accessToken,
                    organizationId: organizationID,
                    view: selectedView
                )
            }.data
        } catch {
            errorMessage = error.localizedDescription
        }
    }
}

private struct ClientSummarySection: View {
    let overview: ClientOverviewPayload

    var body: some View {
        Section {
            VStack(alignment: .leading, spacing: 14) {
                HStack {
                    VStack(alignment: .leading, spacing: 4) {
                        Text(overview.client.name)
                            .font(.title3.bold())
                        if let email = overview.client.email, !email.isEmpty {
                            Text(email)
                                .font(.subheadline)
                                .foregroundStyle(.secondary)
                        }
                    }
                    Spacer()
                    if overview.client.isActive == true {
                        Text("Active")
                            .font(.caption.weight(.semibold))
                            .padding(.horizontal, 9)
                            .padding(.vertical, 4)
                            .background(.green.opacity(0.12), in: Capsule())
                            .foregroundStyle(.green)
                    }
                }

                HStack(spacing: 10) {
                    ClientStatCard(title: "Open", value: overview.counts?.open ?? 0)
                    ClientStatCard(title: "Waiting", value: overview.counts?.waiting ?? 0)
                    ClientStatCard(title: "Done", value: overview.counts?.done ?? 0)
                }

                if let timeLabel = overview.time?.minutesLabel, !timeLabel.isEmpty {
                    Label("This month: \(timeLabel)", systemImage: "clock")
                        .font(.subheadline)
                        .foregroundStyle(.secondary)
                }
            }
            .padding(.vertical, 4)
        }
    }
}

private struct ClientStatCard: View {
    let title: String
    let value: Int

    var body: some View {
        VStack(alignment: .leading, spacing: 4) {
            Text(title.uppercased())
                .font(.caption2.weight(.semibold))
                .foregroundStyle(.secondary)
            Text("\(value)")
                .font(.headline)
        }
        .frame(maxWidth: .infinity, alignment: .leading)
        .padding(10)
        .background(.quaternary.opacity(0.35), in: RoundedRectangle(cornerRadius: 12))
    }
}

private struct ClientTicketRow: View {
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

            HStack(spacing: 8) {
                TicketStatusBadge(status: ticket.status)
                if let updatedAt = ticket.updatedAt, !updatedAt.isEmpty {
                    Text(updatedAt)
                        .font(.caption)
                        .foregroundStyle(.secondary)
                }
                Spacer()
            }
        }
        .padding(.vertical, 4)
    }
}

private struct ClientContactsSection: View {
    let contacts: [ClientContact]

    var body: some View {
        Section("Contacts") {
            if contacts.isEmpty {
                Text("No contacts")
                    .foregroundStyle(.secondary)
            } else {
                ForEach(contacts) { contact in
                    VStack(alignment: .leading, spacing: 4) {
                        HStack {
                            Text(contact.name?.isEmpty == false ? contact.name ?? "Contact" : "Contact")
                                .font(.headline)
                            if contact.isPrimary == true {
                                Text("Primary")
                                    .font(.caption.weight(.semibold))
                                    .padding(.horizontal, 8)
                                    .padding(.vertical, 3)
                                    .background(.blue.opacity(0.12), in: Capsule())
                                    .foregroundStyle(.blue)
                            }
                        }
                        if let email = contact.email, !email.isEmpty {
                            Text(email)
                                .font(.subheadline)
                                .foregroundStyle(.secondary)
                        }
                        if let phone = contact.phone, !phone.isEmpty {
                            Text(phone)
                                .font(.subheadline)
                                .foregroundStyle(.secondary)
                        }
                    }
                    .padding(.vertical, 4)
                }
            }
        }
    }
}
