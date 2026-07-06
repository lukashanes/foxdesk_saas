import SwiftUI
import PhotosUI
import UniformTypeIdentifiers
import UIKit
import FoxDeskKit

struct NewTicketView: View {
    @Environment(AppSession.self) private var session
    @Environment(\.dismiss) private var dismiss

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
    @State private var isLoadingOptions = false
    @State private var optionsMessage: String?
    @State private var selectedPhoto: PhotosPickerItem?
    @State private var pendingAttachments: [PendingNewTicketAttachment] = []
    @State private var isFileImporterPresented = false
    @State private var isCameraPresented = false
    @State private var attachmentMessage: String?
    @State private var isSaving = false
    @State private var errorMessage: String?
    @State private var createdTicketID: Int?

    var body: some View {
        NavigationStack {
            Form {
                Section("Ticket") {
                    TextField("Subject", text: $title)
                    TextEditor(text: $details)
                        .frame(minHeight: 160)
                        .overlay(alignment: .topLeading) {
                            if details.isEmpty {
                                Text("Describe the request")
                                    .foregroundStyle(.tertiary)
                                    .padding(.top, 8)
                                    .padding(.leading, 5)
                                    .allowsHitTesting(false)
                            }
                        }
                    TextField("Tags", text: $tags)
                        .textInputAutocapitalization(.never)
                }

                Section("Attachments") {
                    HStack {
                        if UIImagePickerController.isSourceTypeAvailable(.camera) {
                            Button {
                                isCameraPresented = true
                            } label: {
                                Label("Take photo", systemImage: "camera")
                            }
                            .buttonStyle(.bordered)
                            .disabled(isSaving)
                        }

                        PhotosPicker(selection: $selectedPhoto, matching: .images) {
                            Label("Add photo", systemImage: "photo")
                        }
                        .buttonStyle(.bordered)
                        .disabled(isSaving)

                        Button {
                            isFileImporterPresented = true
                        } label: {
                            Label("Add file", systemImage: "doc.badge.plus")
                        }
                        .buttonStyle(.bordered)
                        .disabled(isSaving)
                    }

                    if pendingAttachments.isEmpty {
                        Text("Attach photos or files before creating the ticket.")
                            .font(.footnote)
                            .foregroundStyle(.secondary)
                    } else {
                        ForEach(pendingAttachments) { attachment in
                            HStack {
                                Label(attachment.filename, systemImage: attachment.iconName)
                                    .lineLimit(1)
                                Spacer()
                                Text(attachment.sizeLabel)
                                    .font(.caption)
                                    .foregroundStyle(.secondary)
                                Button(role: .destructive) {
                                    pendingAttachments.removeAll { $0.id == attachment.id }
                                } label: {
                                    Image(systemName: "xmark.circle.fill")
                                }
                                .buttonStyle(.plain)
                                .disabled(isSaving)
                            }
                        }
                    }

                    if let attachmentMessage {
                        Text(attachmentMessage)
                            .font(.footnote)
                            .foregroundStyle(.secondary)
                    }
                }

                Section("Routing") {
                    if isLoadingOptions {
                        HStack {
                            ProgressView()
                            Text("Loading ticket options")
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

                Section("Schedule") {
                    Toggle("Set due date", isOn: $hasDueDate)
                    if hasDueDate {
                        DatePicker("Due date", selection: $dueDate, displayedComponents: .date)
                    }
                }

                if let errorMessage {
                    Section {
                        Text(errorMessage)
                            .foregroundStyle(.red)
                    }
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
                    addPendingAttachment(
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
                        dismiss()
                    }
                }
                ToolbarItem(placement: .confirmationAction) {
                    Button {
                        Task { await createTicket() }
                    } label: {
                        if isSaving {
                            ProgressView()
                        } else {
                            Text(createdTicketID == nil ? "Create" : "Retry uploads")
                        }
                    }
                    .disabled(isSaving || title.trimmingCharacters(in: .whitespacesAndNewlines).isEmpty)
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
            dismiss()
            await onCreated(ticketID)
        } catch {
            if createdTicketID != nil {
                errorMessage = "Ticket created, but attachment upload failed. Tap Retry uploads to finish."
            } else {
                errorMessage = error.localizedDescription
            }
        }
    }

    private func addSelectedPhoto(_ item: PhotosPickerItem) async {
        guard let data = try? await item.loadTransferable(type: Data.self) else {
            attachmentMessage = "Could not read selected photo."
            selectedPhoto = nil
            return
        }
        let type = item.supportedContentTypes.first ?? .jpeg
        let ext = type.preferredFilenameExtension ?? "jpg"
        addPendingAttachment(
            data: data,
            filename: "photo-\(Int(Date().timeIntervalSince1970)).\(ext)",
            mimeType: type.preferredMIMEType ?? "image/jpeg"
        )
        selectedPhoto = nil
    }

    private func addSelectedFile(_ url: URL) async {
        let didStartAccessing = url.startAccessingSecurityScopedResource()
        defer {
            if didStartAccessing {
                url.stopAccessingSecurityScopedResource()
            }
        }

        do {
            let data = try Data(contentsOf: url)
            let mimeType = UTType(filenameExtension: url.pathExtension)?.preferredMIMEType ?? "application/octet-stream"
            addPendingAttachment(data: data, filename: url.lastPathComponent, mimeType: mimeType)
        } catch {
            attachmentMessage = error.localizedDescription
        }
    }

    private func addPendingAttachment(data: Data, filename: String, mimeType: String) {
        pendingAttachments.append(PendingNewTicketAttachment(data: data, filename: filename, mimeType: mimeType))
        attachmentMessage = "\(pendingAttachments.count) attachment\(pendingAttachments.count == 1 ? "" : "s") ready"
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
                        data: attachment.data
                    )
                    uploadState.markUploaded(attachment.id)
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

    private static let apiDateFormatter: DateFormatter = {
        let formatter = DateFormatter()
        formatter.calendar = Calendar(identifier: .gregorian)
        formatter.locale = Locale(identifier: "en_US_POSIX")
        formatter.dateFormat = "yyyy-MM-dd"
        return formatter
    }()
}

private struct PendingNewTicketAttachment: Identifiable, Sendable {
    let id = UUID()
    let data: Data
    let filename: String
    let mimeType: String

    var sizeLabel: String {
        ByteCountFormatter.string(fromByteCount: Int64(data.count), countStyle: .file)
    }

    var iconName: String {
        mimeType.lowercased().hasPrefix("image/") ? "photo" : "paperclip"
    }
}
