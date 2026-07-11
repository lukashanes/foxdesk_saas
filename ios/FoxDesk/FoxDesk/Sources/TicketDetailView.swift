import SwiftUI
import FoxDeskKit

struct TicketDetailView: View {
    @Environment(AppSession.self) private var session

    let ticketID: Int
    let ticketHash: String?

    private let detailCache = TicketDetailCacheStore()

    @State private var detail: TicketDetailPayload?
    @State private var isLoading = false
    @State private var errorMessage: String?
    @State private var cacheMessage: String?
    @State private var isManagePresented = false

    init(ticketID: Int, ticketHash: String? = nil) {
        self.ticketID = ticketID
        self.ticketHash = ticketHash
    }

    var body: some View {
        List {
            if let cacheMessage {
                Section {
                    Label(cacheMessage, systemImage: "clock.arrow.circlepath")
                        .font(.subheadline)
                        .foregroundStyle(.secondary)
                }
            }

            if let errorMessage, detail == nil {
                Section {
                    ContentUnavailableView("Could not load ticket", systemImage: "exclamationmark.triangle", description: Text(errorMessage))
                }
            }

            if let detail {
                TicketHeaderSection(ticket: detail.ticket)

                TimerControlSection(ticketID: detail.ticket.id) {
                    await loadDetail()
                }

                CommentComposerSection(ticketID: detail.ticket.id) {
                    await loadDetail()
                }

                TicketActivitySections(comments: detail.comments, timeEntries: detail.timeEntries)

                Section("Attachments") {
                    AttachmentUploadSection(ticketID: detail.ticket.id) {
                        await loadDetail()
                    }

                    if detail.attachments.isEmpty {
                        Text("No attachments yet")
                            .foregroundStyle(.secondary)
                    } else {
                        ForEach(detail.attachments) { attachment in
                            AttachmentRow(attachment: attachment)
                        }
                    }
                }
            } else if isLoading {
                Section {
                    HStack {
                        ProgressView()
                        Text("Loading ticket")
                            .foregroundStyle(.secondary)
                    }
                }
            }
        }
        .navigationTitle(detail?.ticket.code ?? "Ticket")
        .navigationBarTitleDisplayMode(.inline)
        .toolbar {
            ToolbarItem(placement: .topBarTrailing) {
                HStack {
                    if canManageTicket {
                        Button("Manage") {
                            isManagePresented = true
                        }
                    }

                    Button {
                        Task { await loadDetail() }
                    } label: {
                        Image(systemName: "arrow.clockwise")
                    }
                    .disabled(isLoading)
                }
            }
        }
        .sheet(isPresented: $isManagePresented) {
            if let detail {
                TicketManageSheet(detail: detail) {
                    await loadDetail()
                }
            }
        }
        .task {
            await loadDetail()
        }
        .refreshable {
            await loadDetail()
        }
    }

    private var canManageTicket: Bool {
        guard let actions = detail?.actions else { return false }
        return !(actions.statuses ?? []).isEmpty
            || !(actions.priorities ?? []).isEmpty
            || !(actions.assignees ?? []).isEmpty
    }

    private func loadDetail() async {
        isLoading = true
        errorMessage = nil
        if detail == nil {
            await loadCachedDetail(message: "Showing saved ticket while FoxDesk refreshes.")
        }
        defer { isLoading = false }

        do {
            let freshDetail = try await session.authenticated { accessToken in
                if let ticketHash, !ticketHash.isEmpty {
                    return try await session.client.ticketDetail(accessToken: accessToken, ticketHash: ticketHash)
                }
                return try await session.client.ticketDetail(accessToken: accessToken, ticketId: ticketID)
            }.data
            detail = freshDetail
            cacheMessage = nil
            if let userId = session.user?.id {
                try? await detailCache.save(
                    userId: userId,
                    tenantId: cacheTenantId,
                    ticketId: freshDetail.ticket.id,
                    detail: freshDetail
                )
            }
        } catch {
            if detail == nil {
                await loadCachedDetail(message: "Showing saved ticket. Pull to refresh when you are back online.")
            }
            if detail == nil {
                errorMessage = error.localizedDescription
            } else {
                cacheMessage = "Showing saved ticket. Pull to refresh when you are back online."
            }
        }
    }

    private func loadCachedDetail(message: String) async {
        guard ticketHash?.isEmpty != false else {
            return
        }
        guard let userId = session.user?.id,
              let cached = try? await detailCache.load(userId: userId, tenantId: cacheTenantId, ticketId: ticketID) else {
            return
        }
        detail = cached.detail
        cacheMessage = message
    }

    private var cacheTenantId: Int {
        session.tenantState?.tenant.id ?? session.user?.tenantId ?? 0
    }
}

private struct TicketHeaderSection: View {
    let ticket: TicketSummary

    var body: some View {
        Section {
            VStack(alignment: .leading, spacing: 12) {
                Text(ticket.title)
                    .font(.title3.bold())

                if let text = ticket.descriptionText, !text.isEmpty {
                    Text(text)
                        .font(.body)
                        .foregroundStyle(.secondary)
                }

                HStack(spacing: 8) {
                    TicketStatusBadge(status: ticket.status)
                    if let priority = ticket.priority?.name, !priority.isEmpty {
                        Label(priority, systemImage: "flag")
                            .font(.caption)
                            .foregroundStyle(.secondary)
                    }
                    if let client = ticket.client?.name, !client.isEmpty {
                        Label(client, systemImage: "building.2")
                            .font(.caption)
                            .foregroundStyle(.secondary)
                    }
                    Label(ticket.workedLabel ?? "0 min", systemImage: "clock")
                        .font(.caption.weight(.medium))
                        .foregroundStyle(.secondary)
                }

                if let organizationID = ticket.client?.id {
                    NavigationLink {
                        ClientContextView(
                            organizationID: organizationID,
                            fallbackName: ticket.client?.name?.isEmpty == false ? ticket.client?.name ?? "Client" : "Client"
                        )
                    } label: {
                        Label("Open client context", systemImage: "building.2.crop.circle")
                    }
                }
            }
            .padding(.vertical, 4)
        }
    }
}
