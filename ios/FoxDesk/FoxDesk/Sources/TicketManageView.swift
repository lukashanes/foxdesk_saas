import SwiftUI
import FoxDeskKit

struct TicketManageSheet: View {
    @Environment(AppSession.self) private var session
    @Environment(\.dismiss) private var dismiss

    let detail: TicketDetailPayload
    let onSaved: () async -> Void

    @State private var statusSelection: Int
    @State private var prioritySelection: Int
    @State private var assigneeSelection: Int
    @State private var isSaving = false
    @State private var message: String?

    init(detail: TicketDetailPayload, onSaved: @escaping () async -> Void) {
        self.detail = detail
        self.onSaved = onSaved
        _statusSelection = State(initialValue: detail.ticket.status?.id ?? detail.actions?.statuses?.first?.id ?? 0)
        _prioritySelection = State(initialValue: detail.ticket.priority?.id ?? 0)
        _assigneeSelection = State(initialValue: detail.ticket.assignee?.id ?? 0)
    }

    var body: some View {
        NavigationStack {
            Form {
                Section("Ticket") {
                    Text(detail.ticket.title)
                        .font(.headline)
                    if let code = detail.ticket.code {
                        Text(code)
                            .foregroundStyle(.secondary)
                    }
                }

                if !statuses.isEmpty {
                    Section("Status") {
                        Picker("Status", selection: $statusSelection) {
                            ForEach(statuses) { status in
                                Text(status.name).tag(status.id)
                            }
                        }
                    }
                }

                if !priorities.isEmpty {
                    Section("Priority") {
                        Picker("Priority", selection: $prioritySelection) {
                            Text("No priority").tag(0)
                            ForEach(priorities) { priority in
                                Text(priority.name).tag(priority.id)
                            }
                        }
                    }
                }

                if !assignees.isEmpty {
                    Section("Assignee") {
                        Picker("Assignee", selection: $assigneeSelection) {
                            Text("Unassigned").tag(0)
                            ForEach(assignees) { assignee in
                                Text(assignee.name).tag(assignee.id)
                            }
                        }
                    }
                }

                if let message {
                    Section {
                        Text(message)
                            .font(.footnote)
                            .foregroundStyle(.secondary)
                    }
                }
            }
            .navigationTitle("Manage ticket")
            .navigationBarTitleDisplayMode(.inline)
            .toolbar {
                ToolbarItem(placement: .cancellationAction) {
                    Button("Cancel") { dismiss() }
                }
                ToolbarItem(placement: .confirmationAction) {
                    Button {
                        Task { await save() }
                    } label: {
                        if isSaving {
                            ProgressView()
                        } else {
                            Text("Save")
                        }
                    }
                    .disabled(isSaving || !hasChanges || (statusSelection <= 0 && !statuses.isEmpty))
                }
            }
        }
    }

    private var statuses: [TicketStatusOption] {
        detail.actions?.statuses ?? []
    }

    private var priorities: [TicketPriorityOption] {
        detail.actions?.priorities ?? []
    }

    private var assignees: [TicketAssigneeOption] {
        detail.actions?.assignees ?? []
    }

    private var hasChanges: Bool {
        statusSelection != (detail.ticket.status?.id ?? 0)
            || prioritySelection != (detail.ticket.priority?.id ?? 0)
            || assigneeSelection != (detail.ticket.assignee?.id ?? 0)
    }

    private func save() async {
        isSaving = true
        message = nil
        defer { isSaving = false }

        do {
            _ = try await session.authenticated { accessToken in
                try await session.client.updateTicket(
                    accessToken: accessToken,
                    request: UpdateTicketRequest(
                        ticketId: detail.ticket.id,
                        statusId: statusSelection != (detail.ticket.status?.id ?? 0) ? statusSelection : nil,
                        priorityId: prioritySelection == 0 ? nil : prioritySelection,
                        includePriorityId: prioritySelection != (detail.ticket.priority?.id ?? 0),
                        assigneeId: assigneeSelection == 0 ? nil : assigneeSelection,
                        includeAssigneeId: assigneeSelection != (detail.ticket.assignee?.id ?? 0)
                    )
                )
            }
            await onSaved()
            dismiss()
        } catch {
            message = error.localizedDescription
        }
    }
}
