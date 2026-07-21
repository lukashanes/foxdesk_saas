import SwiftUI
import FoxDeskKit

struct TicketActivitySections: View {
    let comments: [TicketComment]
    let timeEntries: [TicketTimeEntry]
    let canEditComment: (TicketComment) -> Bool
    let canDeleteComment: (TicketComment) -> Bool
    let canEditTimeEntry: (TicketTimeEntry) -> Bool
    let canDeleteTimeEntry: (TicketTimeEntry) -> Bool
    let onEdit: (TicketEditableItem) -> Void
    let onDeleteComment: (TicketComment) async -> Void
    let onDeleteTimeEntry: (TicketTimeEntry) async -> Void

    private var timeEntriesByComment: [Int: [TicketTimeEntry]] {
        Dictionary(grouping: timeEntries.filter { ($0.commentId ?? 0) > 0 }) { $0.commentId ?? 0 }
    }

    private var orphanTimeEntries: [TicketTimeEntry] {
        timeEntries.filter { ($0.commentId ?? 0) <= 0 }
    }

    var body: some View {
        Section("Comments") {
            if comments.isEmpty {
                Text("No comments yet")
                    .foregroundStyle(.secondary)
            } else {
                ForEach(comments) { comment in
                    TicketCommentRow(
                        comment: comment,
                        linkedTimeEntries: timeEntriesByComment[comment.id] ?? [],
                        canEdit: canEditComment(comment),
                        canDelete: canDeleteComment(comment),
                        canEditTimeEntry: canEditTimeEntry,
                        canDeleteTimeEntry: canDeleteTimeEntry,
                        onEdit: onEdit,
                        onDelete: onDeleteComment,
                        onDeleteTimeEntry: onDeleteTimeEntry
                    )
                }
            }
        }

        if !orphanTimeEntries.isEmpty {
            Section("Time") {
                ForEach(orphanTimeEntries) { entry in
                    TicketTimeEntryRow(
                        entry: entry,
                        canEdit: canEditTimeEntry(entry),
                        canDelete: canDeleteTimeEntry(entry),
                        onEdit: onEdit,
                        onDelete: onDeleteTimeEntry
                    )
                }
            }
        }
    }
}

private struct TicketCommentRow: View {
    let comment: TicketComment
    let linkedTimeEntries: [TicketTimeEntry]
    let canEdit: Bool
    let canDelete: Bool
    let canEditTimeEntry: (TicketTimeEntry) -> Bool
    let canDeleteTimeEntry: (TicketTimeEntry) -> Bool
    let onEdit: (TicketEditableItem) -> Void
    let onDelete: (TicketComment) async -> Void
    let onDeleteTimeEntry: (TicketTimeEntry) async -> Void

    var body: some View {
        VStack(alignment: .leading, spacing: 8) {
            HStack(alignment: .firstTextBaseline) {
                Text(comment.authorName?.isEmpty == false ? comment.authorName ?? "Unknown" : "Unknown")
                    .font(.headline)
                if comment.isInternal == true {
                    Text("Internal")
                        .font(.caption.weight(.semibold))
                        .padding(.horizontal, 8)
                        .padding(.vertical, 3)
                        .background(.orange.opacity(0.15), in: Capsule())
                        .foregroundStyle(.orange)
                }
                Spacer()
                if let createdAt = comment.createdAt {
                    Text(createdAt)
                        .font(.caption)
                        .foregroundStyle(.secondary)
                }
                if canEdit || canDelete {
                    Menu {
                        if canEdit {
                            Button {
                                onEdit(.comment(comment))
                            } label: {
                                Label("Edit comment", systemImage: "pencil")
                            }
                        }
                        if canDelete {
                            Button(role: .destructive) {
                                Task { await onDelete(comment) }
                            } label: {
                                Label("Delete comment", systemImage: "trash")
                            }
                        }
                    } label: {
                        Image(systemName: "ellipsis.circle")
                            .foregroundStyle(.secondary)
                    }
                    .accessibilityLabel("Comment actions")
                }
            }

            RichCommentText(html: comment.contentHtml, fallback: comment.contentText)

            if !linkedTimeEntries.isEmpty {
                VStack(alignment: .leading, spacing: 6) {
                    ForEach(linkedTimeEntries) { entry in
                        LinkedCommentTimeRow(
                            entry: entry,
                            canEdit: canEditTimeEntry(entry),
                            canDelete: canDeleteTimeEntry(entry),
                            onEdit: onEdit,
                            onDelete: onDeleteTimeEntry
                        )
                    }
                }
                .padding(.top, 2)
            }
        }
        .padding(.vertical, 4)
        .swipeActions(edge: .trailing, allowsFullSwipe: true) {
            if canDelete {
                Button(role: .destructive) {
                    Task { await onDelete(comment) }
                } label: {
                    Label("Delete comment", systemImage: "trash")
                }
            }
        }
    }
}

private struct LinkedCommentTimeRow: View {
    let entry: TicketTimeEntry
    let canEdit: Bool
    let canDelete: Bool
    let onEdit: (TicketEditableItem) -> Void
    let onDelete: (TicketTimeEntry) async -> Void

    var body: some View {
        HStack(spacing: 6) {
            Image(systemName: "clock")
            Text("\(entry.durationMinutes) min")
                .fontWeight(.semibold)
            if let startedAt = entry.startedAt, !startedAt.isEmpty {
                Text(startedAt)
                    .foregroundStyle(.secondary)
            }
            if entry.isBillable == true {
                Text("Billable")
                    .foregroundStyle(.secondary)
            }
            if canEdit || canDelete {
                Spacer(minLength: 4)
                Menu {
                    if canEdit {
                        Button {
                            onEdit(.timeEntry(entry))
                        } label: {
                            Label("Edit time entry", systemImage: "pencil")
                        }
                    }
                    if canDelete {
                        Button(role: .destructive) {
                            Task { await onDelete(entry) }
                        } label: {
                            Label("Delete time entry", systemImage: "trash")
                        }
                    }
                } label: {
                    Image(systemName: "ellipsis.circle")
                        .imageScale(.small)
                }
                .buttonStyle(.plain)
                .accessibilityLabel("Time entry actions")
            }
        }
        .font(.caption)
        .foregroundStyle(.blue)
        .padding(.horizontal, 10)
        .padding(.vertical, 6)
        .background(Color.blue.opacity(0.10), in: Capsule())
        .contextMenu {
            if canEdit {
                Button {
                    onEdit(.timeEntry(entry))
                } label: {
                    Label("Edit time entry", systemImage: "pencil")
                }
            }
            if canDelete {
                Button(role: .destructive) {
                    Task { await onDelete(entry) }
                } label: {
                    Label("Delete time entry", systemImage: "trash")
                }
            }
        }
    }
}

private struct RichCommentText: View {
    let html: String?
    let fallback: String?

    var body: some View {
        if let attributedText {
            Text(attributedText)
                .foregroundStyle(.secondary)
        } else {
            Text(fallback ?? "")
                .foregroundStyle(.secondary)
        }
    }

    private var attributedText: AttributedString? {
        let source = html?.trimmingCharacters(in: .whitespacesAndNewlines)
        guard let source, !source.isEmpty else {
            return nil
        }
        return MobileRichTextRenderer.attributedString(fromHTML: source)
    }
}

private struct TicketTimeEntryRow: View {
    let entry: TicketTimeEntry
    let canEdit: Bool
    let canDelete: Bool
    let onEdit: (TicketEditableItem) -> Void
    let onDelete: (TicketTimeEntry) async -> Void

    var body: some View {
        HStack {
            VStack(alignment: .leading, spacing: 4) {
                Text(entry.userName?.isEmpty == false ? entry.userName ?? "Unknown" : "Unknown")
                    .font(.headline)
                if let summary = entry.summary, !summary.isEmpty {
                    Text(summary)
                        .font(.subheadline)
                        .foregroundStyle(.secondary)
                        .lineLimit(2)
                }
            }
            Spacer()
            Text("\(entry.durationMinutes) min")
                .font(.headline)
            if canEdit || canDelete {
                Menu {
                    if canEdit {
                        Button {
                            onEdit(.timeEntry(entry))
                        } label: {
                            Label("Edit time entry", systemImage: "pencil")
                        }
                    }
                    if canDelete {
                        Button(role: .destructive) {
                            Task { await onDelete(entry) }
                        } label: {
                            Label("Delete time entry", systemImage: "trash")
                        }
                    }
                } label: {
                    Image(systemName: "ellipsis.circle")
                        .foregroundStyle(.secondary)
                }
                .accessibilityLabel("Time entry actions")
            }
        }
        .swipeActions(edge: .trailing, allowsFullSwipe: true) {
            if canDelete {
                Button(role: .destructive) {
                    Task { await onDelete(entry) }
                } label: {
                    Label("Delete time entry", systemImage: "trash")
                }
            }
        }
    }
}

enum TicketEditableItem: Identifiable {
    case comment(TicketComment)
    case timeEntry(TicketTimeEntry)

    var id: String {
        switch self {
        case .comment(let comment): return "comment-\(comment.id)"
        case .timeEntry(let entry): return "time-\(entry.id)"
        }
    }
}

struct TicketItemEditSheet: View {
    @Environment(AppSession.self) private var session
    @Environment(\.dismiss) private var dismiss

    let item: TicketEditableItem
    let onSaved: () async -> Void

    @State private var commentText: String
    @State private var durationMinutes: Int
    @State private var summary: String
    @State private var isBillable: Bool
    @State private var usesStartTime: Bool
    @State private var startDate: Date
    @State private var isSaving = false
    @State private var message: String?

    init(item: TicketEditableItem, onSaved: @escaping () async -> Void) {
        self.item = item
        self.onSaved = onSaved

        switch item {
        case .comment(let comment):
            _commentText = State(initialValue: comment.contentText ?? "")
            _durationMinutes = State(initialValue: 15)
            _summary = State(initialValue: "")
            _isBillable = State(initialValue: true)
            _usesStartTime = State(initialValue: false)
            _startDate = State(initialValue: Date())
        case .timeEntry(let entry):
            let parsedStart = Self.parseDate(entry.startedAt)
            _commentText = State(initialValue: "")
            _durationMinutes = State(initialValue: max(1, entry.durationMinutes))
            _summary = State(initialValue: entry.summary ?? "")
            _isBillable = State(initialValue: entry.isBillable ?? true)
            _usesStartTime = State(initialValue: parsedStart != nil)
            _startDate = State(initialValue: parsedStart ?? Date().addingTimeInterval(TimeInterval(-max(1, entry.durationMinutes) * 60)))
        }
    }

    var body: some View {
        NavigationStack {
            Form {
                switch item {
                case .comment:
                    Section("Comment") {
                        TextEditor(text: $commentText)
                            .frame(minHeight: 180)
                    }
                    Section {
                        Text("Paragraphs and lines beginning with -, *, or a number are saved with their formatting.")
                            .font(.footnote)
                            .foregroundStyle(.secondary)
                    }
                case .timeEntry:
                    Section("Time") {
                        Stepper(value: $durationMinutes, in: 1...1440, step: 5) {
                            LabeledContent("Duration", value: "\(durationMinutes) min")
                        }
                        TextField("Work summary", text: $summary, axis: .vertical)
                        Toggle("Billable", isOn: $isBillable)
                        Toggle("Keep an exact start time", isOn: $usesStartTime)
                        if usesStartTime {
                            DatePicker("Started", selection: $startDate)
                            LabeledContent("Ended", value: Self.displayDate(endDate))
                        }
                    }
                }

                if let message {
                    Section {
                        Text(message)
                            .font(.footnote)
                            .foregroundStyle(.red)
                    }
                }
            }
            .navigationTitle(title)
            .navigationBarTitleDisplayMode(.inline)
            .toolbar {
                ToolbarItem(placement: .cancellationAction) {
                    Button("Cancel") { dismiss() }
                }
                ToolbarItem(placement: .confirmationAction) {
                    Button("Save") {
                        Task { await save() }
                    }
                    .disabled(isSaving || !isValid)
                }
            }
            .interactiveDismissDisabled(isSaving)
        }
    }

    private var title: String {
        switch item {
        case .comment: return "Edit comment"
        case .timeEntry: return "Edit time entry"
        }
    }

    private var isValid: Bool {
        switch item {
        case .comment:
            return !commentText.trimmingCharacters(in: .whitespacesAndNewlines).isEmpty
        case .timeEntry:
            return durationMinutes > 0
        }
    }

    private var endDate: Date {
        startDate.addingTimeInterval(TimeInterval(durationMinutes * 60))
    }

    private func save() async {
        isSaving = true
        message = nil
        defer { isSaving = false }

        do {
            switch item {
            case .comment(let comment):
                let content = MobileRichTextFormatter.html(
                    from: commentText.trimmingCharacters(in: .whitespacesAndNewlines)
                )
                _ = try await session.authenticated { accessToken in
                    try await session.client.updateComment(
                        accessToken: accessToken,
                        commentId: comment.id,
                        content: content
                    )
                }
            case .timeEntry(let entry):
                _ = try await session.authenticated { accessToken in
                    try await session.client.updateTimeEntry(
                        accessToken: accessToken,
                        timeEntryId: entry.id,
                        durationMinutes: durationMinutes,
                        summary: summary.trimmingCharacters(in: .whitespacesAndNewlines),
                        isBillable: isBillable,
                        startedAt: usesStartTime ? Self.apiDate(startDate) : nil,
                        endedAt: usesStartTime ? Self.apiDate(endDate) : nil
                    )
                }
            }
            await onSaved()
            dismiss()
        } catch {
            message = error.localizedDescription
        }
    }

    private static func parseDate(_ value: String?) -> Date? {
        guard let value, !value.isEmpty else { return nil }
        let formatter = DateFormatter()
        formatter.calendar = Calendar(identifier: .gregorian)
        formatter.locale = Locale(identifier: "en_US_POSIX")
        formatter.timeZone = .current
        formatter.dateFormat = "yyyy-MM-dd HH:mm:ss"
        return formatter.date(from: value)
    }

    private static func apiDate(_ value: Date) -> String {
        let formatter = DateFormatter()
        formatter.calendar = Calendar(identifier: .gregorian)
        formatter.locale = Locale(identifier: "en_US_POSIX")
        formatter.timeZone = .current
        formatter.dateFormat = "yyyy-MM-dd HH:mm:ss"
        return formatter.string(from: value)
    }

    private static func displayDate(_ value: Date) -> String {
        value.formatted(date: .abbreviated, time: .shortened)
    }
}
