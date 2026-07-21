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
    @State private var editableItem: TicketEditableItem?
    @State private var operationError: String?
    @State private var pendingUndos: [PendingTicketUndo] = []

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

                TicketActivitySections(
                    comments: detail.comments,
                    timeEntries: detail.timeEntries,
                    canEditComment: canEditComment,
                    canDeleteComment: canDeleteComment,
                    canEditTimeEntry: canEditTimeEntry,
                    canDeleteTimeEntry: canDeleteTimeEntry,
                    onEdit: { editableItem = $0 },
                    onDeleteComment: deleteComment,
                    onDeleteTimeEntry: deleteTimeEntry
                )

                Section("Attachments") {
                    AttachmentUploadSection(ticketID: detail.ticket.id) {
                        await loadDetail()
                    }

                    if detail.attachments.isEmpty {
                        Text("No attachments yet")
                            .foregroundStyle(.secondary)
                    } else {
                        ForEach(detail.attachments) { attachment in
                            AttachmentRow(
                                attachment: attachment,
                                canDelete: canDeleteAttachment,
                                onDelete: deleteAttachment
                            )
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
            if canManageTicket {
                ToolbarItem(placement: .topBarTrailing) {
                    Button("Manage") {
                        isManagePresented = true
                    }
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
        .sheet(item: $editableItem) { item in
            TicketItemEditSheet(item: item) {
                await loadDetail()
            }
        }
        .task {
            await loadDetail()
        }
        .refreshable {
            await loadDetail()
        }
        .safeAreaInset(edge: .bottom, spacing: 8) {
            if !pendingUndos.isEmpty {
                VStack(spacing: 8) {
                    ForEach(pendingUndos) { pending in
                        TicketUndoBanner(pending: pending) {
                            await undo(pending)
                        }
                    }
                }
                .padding(.horizontal)
                .padding(.bottom, 4)
            }
        }
        .alert(
            "FoxDesk could not complete the action",
            isPresented: Binding(
                get: { operationError != nil },
                set: { if !$0 { operationError = nil } }
            )
        ) {
            Button("OK", role: .cancel) { operationError = nil }
        } message: {
            Text(operationError ?? "Try again.")
        }
    }

    private var canManageTicket: Bool {
        guard detail?.actions != nil,
              let role = session.user?.role.lowercased() else {
            return false
        }
        return ["admin", "owner", "agent"].contains(role)
    }

    private func canDeleteComment(_ comment: TicketComment) -> Bool {
        guard let user = session.user else { return false }
        return isWorkspaceOwnerOrAdmin(user.role) || comment.userId == user.id
    }

    private func canEditComment(_ comment: TicketComment) -> Bool {
        canDeleteComment(comment)
    }

    private func canDeleteTimeEntry(_ entry: TicketTimeEntry) -> Bool {
        guard let user = session.user else { return false }
        if isWorkspaceOwnerOrAdmin(user.role) || entry.userId == user.id {
            return true
        }
        return entry.userId == nil
            && normalizedPersonName(entry.userName) == normalizedPersonName(user.name)
    }

    private func canEditTimeEntry(_ entry: TicketTimeEntry) -> Bool {
        canDeleteTimeEntry(entry)
    }

    private var canDeleteAttachment: Bool {
        guard let role = session.user?.role.lowercased() else { return false }
        return canManageTicket && ["admin", "owner", "agent"].contains(role)
    }

    private func isWorkspaceOwnerOrAdmin(_ role: String) -> Bool {
        ["admin", "owner"].contains(role.lowercased())
    }

    private func normalizedPersonName(_ value: String?) -> String {
        (value ?? "")
            .trimmingCharacters(in: .whitespacesAndNewlines)
            .folding(options: [.caseInsensitive, .diacriticInsensitive], locale: .current)
    }

    private func deleteComment(_ comment: TicketComment) async {
        do {
            let result = try await session.authenticated { accessToken in
                try await session.client.deleteComment(accessToken: accessToken, commentId: comment.id)
            }.data
            removeCommentLocally(comment.id)
            registerUndo(kind: .comment, token: result.undoToken, serverSeconds: result.undoSeconds)
            await loadDetail()
        } catch {
            operationError = error.localizedDescription
        }
    }

    private func deleteTimeEntry(_ entry: TicketTimeEntry) async {
        do {
            let result = try await session.authenticated { accessToken in
                try await session.client.deleteTimeEntry(accessToken: accessToken, timeEntryId: entry.id)
            }.data
            removeTimeEntryLocally(entry.id)
            registerUndo(kind: .timeEntry, token: result.undoToken, serverSeconds: result.undoSeconds)
            await loadDetail()
        } catch {
            operationError = error.localizedDescription
        }
    }

    private func deleteAttachment(_ attachment: TicketAttachment) async {
        do {
            let result = try await session.authenticated { accessToken in
                try await session.client.deleteAttachment(accessToken: accessToken, attachmentId: attachment.id)
            }.data
            removeAttachmentLocally(attachment.id)
            registerUndo(kind: .attachment, token: result.undoToken, serverSeconds: result.undoSeconds)
            await loadDetail()
        } catch {
            operationError = error.localizedDescription
        }
    }

    private func undo(_ pending: PendingTicketUndo) async {
        do {
            _ = try await session.authenticated { accessToken in
                switch pending.kind {
                case .comment:
                    return try await session.client.restoreComment(accessToken: accessToken, undoToken: pending.token)
                case .timeEntry:
                    return try await session.client.restoreTimeEntry(accessToken: accessToken, undoToken: pending.token)
                case .attachment:
                    return try await session.client.restoreAttachment(accessToken: accessToken, undoToken: pending.token)
                }
            }
            pendingUndos.removeAll { $0.id == pending.id }
            await loadDetail()
        } catch {
            if case FoxDeskAPIError.server(let statusCode, _) = error, statusCode == 410 {
                pendingUndos.removeAll { $0.id == pending.id }
            }
            operationError = error.localizedDescription
        }
    }

    private func registerUndo(kind: PendingTicketUndo.Kind, token: String, serverSeconds: Int?) {
        let seconds = max(1, min(serverSeconds ?? 10, 10))
        let pending = PendingTicketUndo(kind: kind, token: token, seconds: seconds)
        pendingUndos.append(pending)
        Task { @MainActor in
            try? await Task.sleep(nanoseconds: UInt64(seconds) * 1_000_000_000)
            pendingUndos.removeAll { $0.id == pending.id }
        }
    }

    private func removeCommentLocally(_ id: Int) {
        guard let detail else { return }
        self.detail = TicketDetailPayload(
            ticket: detail.ticket,
            comments: detail.comments.filter { $0.id != id },
            attachments: detail.attachments,
            timeEntries: detail.timeEntries,
            actions: detail.actions
        )
    }

    private func removeTimeEntryLocally(_ id: Int) {
        guard let detail else { return }
        self.detail = TicketDetailPayload(
            ticket: detail.ticket,
            comments: detail.comments,
            attachments: detail.attachments,
            timeEntries: detail.timeEntries.filter { $0.id != id },
            actions: detail.actions
        )
    }

    private func removeAttachmentLocally(_ id: Int) {
        guard let detail else { return }
        self.detail = TicketDetailPayload(
            ticket: detail.ticket,
            comments: detail.comments,
            attachments: detail.attachments.filter { $0.id != id },
            timeEntries: detail.timeEntries,
            actions: detail.actions
        )
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

private struct PendingTicketUndo: Identifiable, Equatable {
    enum Kind: Equatable {
        case comment
        case timeEntry
        case attachment

        var deletedLabel: String {
            switch self {
            case .comment: return "Comment deleted"
            case .timeEntry: return "Time entry deleted"
            case .attachment: return "Attachment deleted"
            }
        }
    }

    let id = UUID()
    let kind: Kind
    let token: String
    let seconds: Int
}

private struct TicketUndoBanner: View {
    let pending: PendingTicketUndo
    let onUndo: () async -> Void

    var body: some View {
        HStack(spacing: 12) {
            Text(pending.kind.deletedLabel)
                .font(.subheadline.weight(.medium))
            Spacer()
            Button("Undo") {
                Task { await onUndo() }
            }
            .font(.subheadline.bold())
            .accessibilityHint("Available for \(pending.seconds) seconds after deletion")
        }
        .padding(.horizontal, 16)
        .padding(.vertical, 12)
        .background(.regularMaterial, in: RoundedRectangle(cornerRadius: 14, style: .continuous))
        .shadow(color: .black.opacity(0.14), radius: 10, y: 4)
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
