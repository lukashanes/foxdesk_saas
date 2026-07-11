import Foundation
import SwiftUI
import FoxDeskKit

struct CommentComposerSection: View {
    @Environment(AppSession.self) private var session

    let ticketID: Int
    let onSubmitted: () async -> Void

    @State private var content = ""
    @State private var isInternal = false
    @State private var includeTime = false
    @State private var useExactTime = false
    @State private var durationMinutes = 15
    @State private var workDate = Date()
    @State private var startTime = Date().addingTimeInterval(-15 * 60)
    @State private var endTime = Date()
    @State private var isSending = false
    @State private var hasLoadedDraft = false
    @State private var message: String?
    @State private var draftSaveTask: Task<Void, Never>?

    private let draftStore = TicketCommentDraftStore()

    var body: some View {
        Section("Reply") {
            TextEditor(text: $content)
                .frame(minHeight: 110)
                .overlay(alignment: .topLeading) {
                    if content.isEmpty {
                        Text(isInternal ? "Write an internal note" : "Write a reply")
                            .foregroundStyle(.tertiary)
                            .padding(.top, 8)
                            .padding(.leading, 5)
                            .allowsHitTesting(false)
                    }
                }

            Toggle("Internal note", isOn: $isInternal)
            Toggle("Add time", isOn: $includeTime)

            if includeTime {
                Toggle("Set date and time", isOn: $useExactTime)

                if useExactTime {
                    DatePicker("Date", selection: $workDate, displayedComponents: .date)
                    DatePicker("Start", selection: $startTime, displayedComponents: .hourAndMinute)
                    DatePicker("End", selection: $endTime, displayedComponents: .hourAndMinute)
                    Text(exactTimeSummary)
                        .font(.footnote)
                        .foregroundStyle(.secondary)
                } else {
                    Stepper(value: $durationMinutes, in: 1...1440, step: 5) {
                        Text("\(durationMinutes) min")
                    }
                }
            }

            if let message {
                Text(message)
                    .font(.footnote)
                    .foregroundStyle(.secondary)
            }

            Button {
                Task { await submit() }
            } label: {
                HStack {
                    if isSending {
                        ProgressView()
                    }
                    Text(includeTime ? "Send with time" : "Send")
                }
                .frame(maxWidth: .infinity)
            }
            .buttonStyle(.borderedProminent)
            .disabled(isSending || content.trimmingCharacters(in: .whitespacesAndNewlines).isEmpty)
        }
        .task {
            await loadDraftIfNeeded()
        }
        .onChange(of: content) { _, _ in
            persistDraft()
        }
        .onChange(of: isInternal) { _, _ in
            persistDraft()
        }
        .onChange(of: includeTime) { _, _ in
            persistDraft()
        }
        .onChange(of: useExactTime) { _, _ in
            persistDraft()
        }
        .onChange(of: durationMinutes) { _, _ in
            persistDraft()
        }
        .onChange(of: workDate) { _, _ in
            persistDraft()
        }
        .onChange(of: startTime) { _, _ in
            persistDraft()
        }
        .onChange(of: endTime) { _, _ in
            persistDraft()
        }
        .onDisappear {
            persistDraft(delay: false)
        }
    }

    private func submit() async {
        let trimmed = content.trimmingCharacters(in: .whitespacesAndNewlines)
        guard !trimmed.isEmpty else { return }

        isSending = true
        message = nil
        defer { isSending = false }

        do {
            _ = try await session.authenticated { accessToken in
                try await session.client.addComment(
                    accessToken: accessToken,
                    request: AddCommentRequest(
                        ticketId: ticketID,
                        content: MobileRichTextFormatter.html(from: trimmed),
                        isInternal: isInternal,
                        durationMinutes: includeTime ? resolvedDurationMinutes : nil,
                        isBillable: includeTime ? true : nil,
                        timeSummary: includeTime ? trimmed : nil,
                        manualDate: includeTime && useExactTime ? dateString(from: workDate) : nil,
                        manualStartTime: includeTime && useExactTime ? timeString(from: startTime) : nil,
                        manualEndTime: includeTime && useExactTime ? timeString(from: endTime) : nil,
                        createdAt: includeTime && useExactTime ? createdAtString() : nil
                    )
                )
            }
            draftSaveTask?.cancel()
            draftSaveTask = nil
            content = ""
            includeTime = false
            useExactTime = false
            if let userId = session.user?.id {
                await draftStore.clear(ticketId: ticketID, userId: userId)
            }
            message = "Saved"
            await onSubmitted()
        } catch {
            message = error.localizedDescription
        }
    }

    private func loadDraftIfNeeded() async {
        guard !hasLoadedDraft else { return }
        hasLoadedDraft = true
        guard let userId = session.user?.id else { return }

        do {
            guard let draft = try await draftStore.load(ticketId: ticketID, userId: userId) else {
                return
            }
            content = draft.content
            isInternal = draft.isInternal
            includeTime = draft.includeTime
            useExactTime = draft.useExactTime
            durationMinutes = draft.durationMinutes
            workDate = draft.workDate
            startTime = draft.startTime
            endTime = draft.endTime
            message = "Draft restored"
        } catch {
            message = "Could not restore draft."
        }
    }

    private func persistDraft(delay: Bool = true) {
        guard hasLoadedDraft, let userId = session.user?.id else { return }
        let draft = TicketCommentDraft(
            ticketId: ticketID,
            userId: userId,
            content: content,
            isInternal: isInternal,
            includeTime: includeTime,
            useExactTime: useExactTime,
            durationMinutes: durationMinutes,
            workDate: workDate,
            startTime: startTime,
            endTime: endTime
        )
        draftSaveTask?.cancel()
        draftSaveTask = Task {
            if delay {
                try? await Task.sleep(for: .milliseconds(250))
            }
            guard !Task.isCancelled else { return }
            try? await draftStore.save(draft)
        }
    }

    private var resolvedDurationMinutes: Int {
        useExactTime ? exactDurationMinutes : durationMinutes
    }

    private var exactDurationMinutes: Int {
        let interval = exactTimeRange().end.timeIntervalSince(exactTimeRange().start)
        return max(1, Int(interval / 60))
    }

    private var exactTimeSummary: String {
        let range = exactTimeRange()
        return "\(exactDurationMinutes) min will be logged on \(dateString(from: workDate)), \(timeString(from: range.start))-\(timeString(from: range.end))"
    }

    private func exactTimeRange() -> (start: Date, end: Date) {
        let calendar = Calendar.current
        let dateParts = calendar.dateComponents([.year, .month, .day], from: workDate)
        let startParts = calendar.dateComponents([.hour, .minute], from: startTime)
        let endParts = calendar.dateComponents([.hour, .minute], from: endTime)

        let start = calendar.date(from: DateComponents(
            year: dateParts.year,
            month: dateParts.month,
            day: dateParts.day,
            hour: startParts.hour,
            minute: startParts.minute
        )) ?? workDate

        var end = calendar.date(from: DateComponents(
            year: dateParts.year,
            month: dateParts.month,
            day: dateParts.day,
            hour: endParts.hour,
            minute: endParts.minute
        )) ?? start.addingTimeInterval(TimeInterval(durationMinutes * 60))

        if end <= start {
            end = calendar.date(byAdding: .day, value: 1, to: end) ?? start.addingTimeInterval(TimeInterval(durationMinutes * 60))
        }

        return (start, end)
    }

    private func createdAtString() -> String {
        dateTimeString(from: exactTimeRange().end)
    }

    private func dateString(from date: Date) -> String {
        formatted(date, pattern: "yyyy-MM-dd")
    }

    private func timeString(from date: Date) -> String {
        formatted(date, pattern: "HH:mm")
    }

    private func dateTimeString(from date: Date) -> String {
        formatted(date, pattern: "yyyy-MM-dd HH:mm:ss")
    }

    private func formatted(_ date: Date, pattern: String) -> String {
        let formatter = DateFormatter()
        formatter.calendar = Calendar(identifier: .gregorian)
        formatter.locale = Locale(identifier: "en_US_POSIX")
        formatter.timeZone = .current
        formatter.dateFormat = pattern
        return formatter.string(from: date)
    }
}
