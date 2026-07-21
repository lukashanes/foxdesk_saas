import SwiftUI
import PhotosUI
import UniformTypeIdentifiers
import UIKit
import FoxDeskKit

struct NewTicketView: View {
    @Environment(AppSession.self) private var session

    let onCancel: () -> Void
    let onCreated: (Int) async -> Void

    @State private var title = ""
    @State private var details = ""
    @State private var tags = ""
    @State private var createOptions: CreateTicketOptionsPayload?
    @State private var selectedClientID: Int?
    @State private var selectedAssigneeID: Int?
    @State private var selectedPriorityID: Int?
    @State private var selectedStatusID: Int?
    @State private var hasDueDate = false
    @State private var dueDate = Date()
    @State private var includeWorkedTime = false
    @State private var workedTimeMinutes = 15
    @State private var workDate = Date()
    @State private var useExactWorkTime = false
    @State private var workStartTime = Date().addingTimeInterval(-15 * 60)
    @State private var workEndTime = Date()
    @State private var workNote = ""
    @State private var isBillable = true
    @State private var startTimerAfterCreate = false
    @State private var isLoadingOptions = false
    @State private var optionsMessage: String?
    @State private var selectedPhoto: PhotosPickerItem?
    @State private var pendingAttachments: [StagedAttachmentFile] = []
    @State private var isFileImporterPresented = false
    @State private var isCameraPresented = false
    @State private var attachmentMessage: String?
    @State private var isSaving = false
    @State private var errorMessage: String?
    @State private var createdTicketID: Int?
    @State private var didAddWorkedTime = false
    @State private var didStartTimer = false
    @State private var isMoreOptionsExpanded = false

    var body: some View {
        NavigationStack {
            ZStack {
                Color(uiColor: .systemGroupedBackground)
                    .ignoresSafeArea()

                ScrollView {
                    LazyVStack(spacing: 16) {
                        NewTicketGlassSection(title: "Ticket") {
                            TextField("Subject", text: $title)
                                .font(.headline)
                                .textFieldStyle(.plain)

                            Divider()

                            TextEditor(text: $details)
                                .frame(minHeight: 132)
                                .scrollContentBackground(.hidden)
                                .overlay(alignment: .topLeading) {
                                    if details.isEmpty {
                                        Text("Describe the request")
                                            .foregroundStyle(.tertiary)
                                            .padding(.top, 8)
                                            .padding(.leading, 5)
                                            .allowsHitTesting(false)
                                    }
                                }
                        }

                        NewTicketGlassSection(title: "Add") {
                            HStack(spacing: 10) {
                                Menu {
                                    if UIImagePickerController.isSourceTypeAvailable(.camera) {
                                        Button {
                                            isCameraPresented = true
                                        } label: {
                                            Label("Take photo", systemImage: "camera")
                                        }
                                    }

                                    PhotosPicker(selection: $selectedPhoto, matching: .images) {
                                        Label("Photo library", systemImage: "photo.on.rectangle")
                                    }

                                    Button {
                                        isFileImporterPresented = true
                                    } label: {
                                        Label("Choose file", systemImage: "doc.badge.plus")
                                    }
                                } label: {
                                    NewTicketActionLabel(
                                        title: pendingAttachments.isEmpty ? "Attachment" : "Attachments (\(pendingAttachments.count))",
                                        systemImage: "paperclip"
                                    )
                                }
                                .buttonStyle(NewTicketGlassButtonStyle())
                                .disabled(isSaving)

                                if canTrackTime {
                                    Button {
                                        withAnimation(.snappy) {
                                            includeWorkedTime.toggle()
                                        }
                                    } label: {
                                        NewTicketActionLabel(
                                            title: includeWorkedTime ? durationLabel(resolvedWorkedTimeMinutes) : "Add time",
                                            systemImage: "clock"
                                        )
                                    }
                                    .buttonStyle(NewTicketGlassButtonStyle(isSelected: includeWorkedTime))
                                    .disabled(isSaving)
                                }
                            }

                            pendingAttachmentsView

                            if includeWorkedTime && canTrackTime {
                                Divider()
                                quickTimeView
                            }

                            if canTrackTime {
                                Toggle("Start timer after creating", isOn: $startTimerAfterCreate)
                            }
                        }

                        routingSection
                        moreOptionsSection

                        if let errorMessage {
                            NewTicketGlassSection {
                                Label(errorMessage, systemImage: "exclamationmark.triangle")
                                    .foregroundStyle(.red)
                            }
                        }
                    }
                    .padding(.horizontal, 16)
                    .padding(.vertical, 12)
                }
            }
            .navigationTitle("New ticket")
            .navigationBarTitleDisplayMode(.inline)
            .task {
                await loadCreateOptions()
            }
            .onChange(of: selectedPhoto) { _, item in
                guard let item else { return }
                Task { await addSelectedPhoto(item) }
            }
            .fileImporter(
                isPresented: $isFileImporterPresented,
                allowedContentTypes: [.item],
                allowsMultipleSelection: false
            ) { result in
                switch result {
                case .success(let urls):
                    guard let url = urls.first else { return }
                    Task { await addSelectedFile(url) }
                case .failure(let error):
                    attachmentMessage = error.localizedDescription
                }
            }
            .fullScreenCover(isPresented: $isCameraPresented) {
                CameraCaptureView { data in
                    stagePendingAttachment(
                        data: data,
                        filename: "camera-\(Int(Date().timeIntervalSince1970)).jpg",
                        mimeType: "image/jpeg"
                    )
                }
                .ignoresSafeArea()
            }
            .toolbar {
                ToolbarItem(placement: .cancellationAction) {
                    Button("Cancel") {
                        cancelCreation()
                    }
                }
                ToolbarItem(placement: .confirmationAction) {
                    Button {
                        Task { await createTicket() }
                    } label: {
                        if isSaving {
                            ProgressView()
                        } else {
                            Text(createdTicketID == nil ? "Create" : "Finish setup")
                        }
                    }
                    .disabled(isSaving || title.trimmingCharacters(in: .whitespacesAndNewlines).isEmpty)
                }
            }
            .onDisappear {
                if !isSaving {
                    discardPendingAttachments()
                }
            }
        }
    }

    private func loadCreateOptions() async {
        guard createOptions == nil else { return }

        isLoadingOptions = true
        optionsMessage = nil
        defer { isLoadingOptions = false }

        do {
            let response = try await session.authenticated { accessToken in
                try await session.client.createTicketOptions(accessToken: accessToken)
            }
            createOptions = response.data
            if selectedPriorityID == nil {
                selectedPriorityID = response.data.defaults?.priorityId
            }
            if selectedStatusID == nil {
                selectedStatusID = response.data.defaults?.statusId
            }
        } catch {
            optionsMessage = "You can still create the ticket. Client, priority, and assignee options could not be loaded."
        }
    }

    private func createTicket() async {
        let trimmedTitle = title.trimmingCharacters(in: .whitespacesAndNewlines)
        guard !trimmedTitle.isEmpty else { return }

        isSaving = true
        errorMessage = nil
        defer { isSaving = false }

        do {
            let ticketID: Int
            if let createdTicketID {
                ticketID = createdTicketID
            } else {
                let response = try await session.authenticated { accessToken in
                    try await session.client.createTicket(
                        accessToken: accessToken,
                        request: CreateTicketRequest(
                            title: trimmedTitle,
                            description: MobileRichTextFormatter.html(from: details),
                            organizationId: selectedClientID,
                            assigneeId: selectedAssigneeID,
                            priorityId: selectedPriorityID,
                            statusId: selectedStatusID,
                            dueDate: hasDueDate ? Self.apiDateFormatter.string(from: dueDate) : nil,
                            tags: tags.trimmingCharacters(in: .whitespacesAndNewlines).isEmpty ? nil : tags
                        )
                    )
                }
                ticketID = response.data.ticketId
                createdTicketID = ticketID
            }

            try await uploadPendingAttachments(to: ticketID)
            try await addWorkedTimeIfNeeded(to: ticketID, ticketTitle: trimmedTitle)
            try await startTimerIfNeeded(on: ticketID)
            resetForm()
            await onCreated(ticketID)
        } catch {
            if createdTicketID != nil {
                errorMessage = "Ticket created, but setup is not finished. Tap Finish setup to retry the remaining step."
            } else {
                errorMessage = error.localizedDescription
            }
        }
    }

    private func addSelectedPhoto(_ item: PhotosPickerItem) async {
        defer { selectedPhoto = nil }

        guard let data = try? await item.loadTransferable(type: Data.self) else {
            attachmentMessage = "Could not read selected photo."
            return
        }
        let type = item.supportedContentTypes.first ?? .jpeg
        let ext = type.preferredFilenameExtension ?? "jpg"
        stagePendingAttachment(data: data, filename: "photo-\(Int(Date().timeIntervalSince1970)).\(ext)", mimeType: type.preferredMIMEType ?? "image/jpeg")
    }

    private func addSelectedFile(_ url: URL) async {
        let didStartAccessing = url.startAccessingSecurityScopedResource()
        defer {
            if didStartAccessing {
                url.stopAccessingSecurityScopedResource()
            }
        }

        do {
            let mimeType = UTType(filenameExtension: url.pathExtension)?.preferredMIMEType ?? "application/octet-stream"
            let attachment = try StagedAttachmentFile(
                copying: url,
                filename: url.lastPathComponent,
                mimeType: mimeType
            )
            pendingAttachments.append(attachment)
            updateAttachmentMessage()
        } catch {
            attachmentMessage = error.localizedDescription
        }
    }

    private func stagePendingAttachment(data: Data, filename: String, mimeType: String) {
        do {
            pendingAttachments.append(try StagedAttachmentFile(data: data, filename: filename, mimeType: mimeType))
            updateAttachmentMessage()
        } catch {
            attachmentMessage = error.localizedDescription
        }
    }

    private func uploadPendingAttachments(to ticketID: Int) async throws {
        guard !pendingAttachments.isEmpty else { return }

        let attachmentsToUpload = pendingAttachments
        var uploadState = StagedAttachmentUploadState<UUID>()

        do {
            try await session.authenticated { accessToken in
                for attachment in attachmentsToUpload {
                    _ = try await session.client.uploadAttachment(
                        accessToken: accessToken,
                        ticketId: ticketID,
                        filename: attachment.filename,
                        mimeType: attachment.mimeType,
                        fileURL: attachment.fileURL
                    )
                    uploadState.markUploaded(attachment.id)
                    attachment.remove()
                }
            }
        } catch {
            pendingAttachments = uploadState.remaining(from: pendingAttachments) { $0.id }
            if !pendingAttachments.isEmpty {
                attachmentMessage = "\(pendingAttachments.count) attachment\(pendingAttachments.count == 1 ? "" : "s") still needs upload"
            }
            throw error
        }

        pendingAttachments.removeAll()
        attachmentMessage = nil
    }

    private func addWorkedTimeIfNeeded(to ticketID: Int, ticketTitle: String) async throws {
        guard includeWorkedTime, !didAddWorkedTime else { return }

        let note = workNote.trimmingCharacters(in: .whitespacesAndNewlines)
        let summary = note.isEmpty ? "Initial work on \(ticketTitle)" : note
        let range = exactWorkTimeRange()

        _ = try await session.authenticated { accessToken in
            try await session.client.addComment(
                accessToken: accessToken,
                request: AddCommentRequest(
                    ticketId: ticketID,
                    content: MobileRichTextFormatter.html(from: summary),
                    isInternal: true,
                    skipNotification: true,
                    durationMinutes: resolvedWorkedTimeMinutes,
                    isBillable: isBillable,
                    timeSummary: summary,
                    manualDate: useExactWorkTime ? Self.apiDateFormatter.string(from: workDate) : nil,
                    manualStartTime: useExactWorkTime ? Self.apiTimeFormatter.string(from: range.start) : nil,
                    manualEndTime: useExactWorkTime ? Self.apiTimeFormatter.string(from: range.end) : nil,
                    createdAt: useExactWorkTime
                        ? Self.apiDateTimeFormatter.string(from: range.end)
                        : Self.apiDateTimeFormatter.string(from: workDateWithCurrentTime())
                )
            )
        }
        didAddWorkedTime = true
    }

    private func startTimerIfNeeded(on ticketID: Int) async throws {
        guard startTimerAfterCreate, !didStartTimer else { return }

        _ = try await session.authenticated { accessToken in
            try await session.client.ticketTimerAction(
                accessToken: accessToken,
                ticketId: ticketID,
                action: "start"
            )
        }
        didStartTimer = true
    }

    private func updateAttachmentMessage() {
        attachmentMessage = "\(pendingAttachments.count) attachment\(pendingAttachments.count == 1 ? "" : "s") ready"
    }

    private func discardPendingAttachments() {
        pendingAttachments.forEach { $0.remove() }
        pendingAttachments.removeAll()
    }

    private func cancelCreation() {
        guard !isSaving else { return }
        resetForm()
        onCancel()
    }

    private func resetForm() {
        title = ""
        details = ""
        tags = ""
        selectedClientID = nil
        selectedAssigneeID = nil
        selectedPriorityID = createOptions?.defaults?.priorityId
        selectedStatusID = createOptions?.defaults?.statusId
        hasDueDate = false
        dueDate = Date()
        includeWorkedTime = false
        workedTimeMinutes = 15
        workDate = Date()
        useExactWorkTime = false
        workStartTime = Date().addingTimeInterval(-15 * 60)
        workEndTime = Date()
        workNote = ""
        isBillable = true
        startTimerAfterCreate = false
        selectedPhoto = nil
        discardPendingAttachments()
        attachmentMessage = nil
        errorMessage = nil
        createdTicketID = nil
        didAddWorkedTime = false
        didStartTimer = false
        isMoreOptionsExpanded = false
    }

    @ViewBuilder
    private var pendingAttachmentsView: some View {
        if !pendingAttachments.isEmpty {
            VStack(spacing: 8) {
                ForEach(pendingAttachments) { attachment in
                    HStack(spacing: 10) {
                        Image(systemName: attachment.iconName)
                            .foregroundStyle(.tint)
                        Text(attachment.filename)
                            .lineLimit(1)
                        Spacer()
                        Text(attachment.sizeLabel)
                            .font(.caption)
                            .foregroundStyle(.secondary)
                        Button(role: .destructive) {
                            attachment.remove()
                            pendingAttachments.removeAll { $0.id == attachment.id }
                            updateAttachmentMessage()
                        } label: {
                            Image(systemName: "xmark.circle.fill")
                        }
                        .buttonStyle(.plain)
                        .disabled(isSaving)
                    }
                    .font(.subheadline)
                }
            }
        }

        if let attachmentMessage {
            Text(attachmentMessage)
                .font(.footnote)
                .foregroundStyle(.secondary)
        }
    }

    private var quickTimeView: some View {
        VStack(alignment: .leading, spacing: 14) {
            Text("Worked time")
                .font(.subheadline.weight(.semibold))

            HStack(spacing: 8) {
                ForEach([5, 15, 30, 60], id: \.self) { minutes in
                    Button(minutes == 60 ? "1 h" : "\(minutes) min") {
                        useExactWorkTime = false
                        workedTimeMinutes = minutes
                    }
                    .buttonStyle(NewTicketTimeChipStyle(isSelected: !useExactWorkTime && workedTimeMinutes == minutes))
                }
            }

            DatePicker("Date", selection: $workDate, displayedComponents: .date)
            Toggle("Exact start and end", isOn: $useExactWorkTime)

            if useExactWorkTime {
                HStack {
                    DatePicker("Start", selection: $workStartTime, displayedComponents: .hourAndMinute)
                    DatePicker("End", selection: $workEndTime, displayedComponents: .hourAndMinute)
                }
                LabeledContent("Duration", value: durationLabel(resolvedWorkedTimeMinutes))
                    .font(.subheadline)
            }

            TextField("What was done?", text: $workNote, axis: .vertical)
                .lineLimit(1...3)
                .textFieldStyle(.plain)
                .padding(12)
                .background(.background.opacity(0.55), in: RoundedRectangle(cornerRadius: 12, style: .continuous))

            Toggle("Billable", isOn: $isBillable)
        }
    }

    private var routingSection: some View {
        NewTicketGlassSection(title: "Routing") {
            if isLoadingOptions {
                HStack {
                    ProgressView()
                    Text("Loading options")
                        .foregroundStyle(.secondary)
                }
            } else if let createOptions {
                if !createOptions.clients.isEmpty {
                    Picker("Client", selection: $selectedClientID) {
                        Text("No client").tag(Optional<Int>.none)
                        ForEach(createOptions.clients) { client in
                            Text(client.name).tag(Optional(client.id))
                        }
                    }
                }

                if !createOptions.assignees.isEmpty {
                    Picker("Assignee", selection: $selectedAssigneeID) {
                        Text("Unassigned").tag(Optional<Int>.none)
                        ForEach(createOptions.assignees) { assignee in
                            Text(assignee.name).tag(Optional(assignee.id))
                        }
                    }
                }
            }

            if let optionsMessage {
                Text(optionsMessage)
                    .font(.footnote)
                    .foregroundStyle(.secondary)
            }
        }
    }

    private var moreOptionsSection: some View {
        NewTicketGlassSection {
            DisclosureGroup("More options", isExpanded: $isMoreOptionsExpanded) {
                VStack(spacing: 14) {
                    TextField("Tags", text: $tags)
                        .textInputAutocapitalization(.never)

                    if let createOptions {
                        if !createOptions.priorities.isEmpty {
                            Picker("Priority", selection: $selectedPriorityID) {
                                Text("Default").tag(Optional<Int>.none)
                                ForEach(createOptions.priorities) { priority in
                                    Text(priority.name).tag(Optional(priority.id))
                                }
                            }
                        }

                        if !createOptions.statuses.isEmpty {
                            Picker("Status", selection: $selectedStatusID) {
                                Text("Default").tag(Optional<Int>.none)
                                ForEach(createOptions.statuses) { status in
                                    Text(status.name).tag(Optional(status.id))
                                }
                            }
                        }
                    }

                    Toggle("Set due date", isOn: $hasDueDate)
                    if hasDueDate {
                        DatePicker("Due date", selection: $dueDate, displayedComponents: .date)
                    }
                }
                .padding(.top, 12)
            }
            .font(.subheadline.weight(.semibold))
        }
    }

    private var canTrackTime: Bool {
        guard let role = session.user?.role.lowercased() else { return false }
        return role == "admin" || role == "agent"
    }

    private var resolvedWorkedTimeMinutes: Int {
        useExactWorkTime ? max(1, Int(exactWorkTimeRange().end.timeIntervalSince(exactWorkTimeRange().start) / 60)) : workedTimeMinutes
    }

    private func exactWorkTimeRange() -> (start: Date, end: Date) {
        let calendar = Calendar.current
        let date = calendar.dateComponents([.year, .month, .day], from: workDate)
        let start = calendar.dateComponents([.hour, .minute], from: workStartTime)
        let end = calendar.dateComponents([.hour, .minute], from: workEndTime)
        let startDate = calendar.date(from: DateComponents(
            year: date.year, month: date.month, day: date.day,
            hour: start.hour, minute: start.minute
        )) ?? workDate
        var endDate = calendar.date(from: DateComponents(
            year: date.year, month: date.month, day: date.day,
            hour: end.hour, minute: end.minute
        )) ?? startDate.addingTimeInterval(TimeInterval(workedTimeMinutes * 60))
        if endDate <= startDate {
            endDate = calendar.date(byAdding: .day, value: 1, to: endDate) ?? startDate.addingTimeInterval(TimeInterval(workedTimeMinutes * 60))
        }
        return (startDate, endDate)
    }

    private func workDateWithCurrentTime() -> Date {
        let calendar = Calendar.current
        let date = calendar.dateComponents([.year, .month, .day], from: workDate)
        let time = calendar.dateComponents([.hour, .minute], from: Date())
        return calendar.date(from: DateComponents(
            year: date.year, month: date.month, day: date.day,
            hour: time.hour, minute: time.minute
        )) ?? workDate
    }

    private func durationLabel(_ minutes: Int) -> String {
        let hours = minutes / 60
        let remainder = minutes % 60
        if hours == 0 { return "\(remainder) min" }
        if remainder == 0 { return "\(hours) h" }
        return "\(hours) h \(remainder) min"
    }

    private static let apiDateFormatter: DateFormatter = {
        let formatter = DateFormatter()
        formatter.calendar = Calendar(identifier: .gregorian)
        formatter.locale = Locale(identifier: "en_US_POSIX")
        formatter.dateFormat = "yyyy-MM-dd"
        return formatter
    }()

    private static let apiTimeFormatter: DateFormatter = {
        let formatter = DateFormatter()
        formatter.calendar = Calendar(identifier: .gregorian)
        formatter.locale = Locale(identifier: "en_US_POSIX")
        formatter.dateFormat = "HH:mm"
        return formatter
    }()

    private static let apiDateTimeFormatter: DateFormatter = {
        let formatter = DateFormatter()
        formatter.calendar = Calendar(identifier: .gregorian)
        formatter.locale = Locale(identifier: "en_US_POSIX")
        formatter.dateFormat = "yyyy-MM-dd HH:mm:ss"
        return formatter
    }()
}

private struct NewTicketGlassSection<Content: View>: View {
    let title: String?
    let content: Content

    init(title: String? = nil, @ViewBuilder content: () -> Content) {
        self.title = title
        self.content = content()
    }

    var body: some View {
        VStack(alignment: .leading, spacing: 14) {
            if let title {
                Text(title)
                    .font(.subheadline.weight(.semibold))
                    .foregroundStyle(.secondary)
            }

            content
        }
        .padding(16)
        .frame(maxWidth: .infinity, alignment: .leading)
        .background(.regularMaterial, in: RoundedRectangle(cornerRadius: 18, style: .continuous))
        .overlay {
            RoundedRectangle(cornerRadius: 18, style: .continuous)
                .stroke(Color.primary.opacity(0.08), lineWidth: 1)
        }
    }
}

private struct NewTicketActionLabel: View {
    let title: String
    let systemImage: String

    var body: some View {
        Label(title, systemImage: systemImage)
            .font(.subheadline.weight(.semibold))
            .lineLimit(1)
            .frame(maxWidth: .infinity)
    }
}

private struct NewTicketGlassButtonStyle: ButtonStyle {
    var isSelected = false

    func makeBody(configuration: Configuration) -> some View {
        configuration.label
            .foregroundStyle(isSelected ? Color.accentColor : Color.primary)
            .padding(.horizontal, 12)
            .frame(maxWidth: .infinity, minHeight: 44)
            .background(.thinMaterial, in: RoundedRectangle(cornerRadius: 14, style: .continuous))
            .overlay {
                RoundedRectangle(cornerRadius: 14, style: .continuous)
                    .fill(isSelected ? Color.accentColor.opacity(0.13) : Color.clear)
            }
            .overlay {
                RoundedRectangle(cornerRadius: 14, style: .continuous)
                    .stroke(
                        isSelected ? Color.accentColor.opacity(0.42) : Color.primary.opacity(0.09),
                        lineWidth: 1
                    )
            }
            .scaleEffect(configuration.isPressed ? 0.98 : 1)
            .opacity(configuration.isPressed ? 0.84 : 1)
            .animation(.easeOut(duration: 0.12), value: configuration.isPressed)
    }
}

private struct NewTicketTimeChipStyle: ButtonStyle {
    let isSelected: Bool

    func makeBody(configuration: Configuration) -> some View {
        configuration.label
            .font(.subheadline.weight(.semibold))
            .foregroundStyle(isSelected ? Color.white : Color.primary)
            .padding(.horizontal, 12)
            .frame(maxWidth: .infinity, minHeight: 36)
            .background(.thinMaterial, in: Capsule())
            .overlay {
                Capsule()
                    .fill(isSelected ? Color.accentColor : Color.clear)
            }
            .overlay {
                Capsule()
                    .stroke(isSelected ? Color.clear : Color.primary.opacity(0.09), lineWidth: 1)
            }
            .scaleEffect(configuration.isPressed ? 0.97 : 1)
            .animation(.easeOut(duration: 0.12), value: configuration.isPressed)
    }
}
