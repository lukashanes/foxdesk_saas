import SwiftUI
import PhotosUI
import UniformTypeIdentifiers
import UIKit
import FoxDeskKit

struct AttachmentUploadSection: View {
    @Environment(AppSession.self) private var session

    let ticketID: Int
    let onUploaded: () async -> Void

    @State private var selectedPhoto: PhotosPickerItem?
    @State private var isFileImporterPresented = false
    @State private var isCameraPresented = false
    @State private var isUploading = false
    @State private var message: String?
    @State private var failedUpload: PendingAttachmentUpload?

    var body: some View {
        VStack(alignment: .leading, spacing: 8) {
            HStack {
                if UIImagePickerController.isSourceTypeAvailable(.camera) {
                    Button {
                        isCameraPresented = true
                    } label: {
                        Label("Take photo", systemImage: "camera")
                    }
                    .buttonStyle(.bordered)
                    .disabled(isUploading)
                }

                PhotosPicker(selection: $selectedPhoto, matching: .images) {
                    Label("Add photo", systemImage: "photo")
                }
                .buttonStyle(.bordered)
                .disabled(isUploading)

                Button {
                    isFileImporterPresented = true
                } label: {
                    Label("Add file", systemImage: "doc.badge.plus")
                }
                .buttonStyle(.bordered)
                .disabled(isUploading)

                Spacer()

                if isUploading {
                    ProgressView()
                }
            }

            if let message {
                Text(message)
                    .font(.footnote)
                    .foregroundStyle(.secondary)
            }

            if let failedUpload {
                VStack(alignment: .leading, spacing: 8) {
                    Label {
                        Text("Upload failed: \(failedUpload.filename)")
                    } icon: {
                        Image(systemName: "exclamationmark.triangle")
                    }
                    .font(.footnote)
                    .foregroundStyle(.orange)

                    Button {
                        Task { await retryFailedUpload() }
                    } label: {
                        Label("Retry upload", systemImage: "arrow.clockwise")
                    }
                    .buttonStyle(.bordered)
                    .disabled(isUploading)
                }
                .padding(.top, 4)
            }
        }
        .onChange(of: selectedPhoto) { _, item in
            guard let item else { return }
            Task { await uploadPhoto(item) }
        }
        .fileImporter(
            isPresented: $isFileImporterPresented,
            allowedContentTypes: [.item],
            allowsMultipleSelection: false
        ) { result in
            switch result {
            case .success(let urls):
                guard let url = urls.first else { return }
                Task { await uploadFile(url) }
            case .failure(let error):
                message = error.localizedDescription
            }
        }
        .fullScreenCover(isPresented: $isCameraPresented) {
            CameraCaptureView { data in
                Task { await uploadCameraPhoto(data) }
            }
            .ignoresSafeArea()
        }
    }

    private func uploadPhoto(_ item: PhotosPickerItem) async {
        guard let data = try? await item.loadTransferable(type: Data.self) else {
            message = "Could not read selected photo."
            return
        }
        let type = item.supportedContentTypes.first ?? .jpeg
        let ext = type.preferredFilenameExtension ?? "jpg"
        await uploadData(
            data,
            filename: "photo-\(Int(Date().timeIntervalSince1970)).\(ext)",
            mimeType: type.preferredMIMEType ?? "image/jpeg"
        )
        selectedPhoto = nil
    }

    private func uploadCameraPhoto(_ data: Data) async {
        await uploadData(
            data,
            filename: "camera-\(Int(Date().timeIntervalSince1970)).jpg",
            mimeType: "image/jpeg"
        )
    }

    private func uploadFile(_ url: URL) async {
        let didStartAccessing = url.startAccessingSecurityScopedResource()
        defer {
            if didStartAccessing {
                url.stopAccessingSecurityScopedResource()
            }
        }

        do {
            let data = try Data(contentsOf: url)
            let mimeType = UTType(filenameExtension: url.pathExtension)?.preferredMIMEType ?? "application/octet-stream"
            await uploadData(data, filename: url.lastPathComponent, mimeType: mimeType)
        } catch {
            message = error.localizedDescription
        }
    }

    private func uploadData(_ data: Data, filename: String, mimeType: String) async {
        isUploading = true
        message = nil
        defer { isUploading = false }

        do {
            _ = try await session.authenticated { accessToken in
                try await session.client.uploadAttachment(
                    accessToken: accessToken,
                    ticketId: ticketID,
                    filename: filename,
                    mimeType: mimeType,
                    data: data
                )
            }
            message = "Attachment uploaded"
            failedUpload = nil
            await onUploaded()
        } catch {
            failedUpload = PendingAttachmentUpload(data: data, filename: filename, mimeType: mimeType)
            message = error.localizedDescription
        }
    }

    private func retryFailedUpload() async {
        guard let failedUpload else { return }
        await uploadData(failedUpload.data, filename: failedUpload.filename, mimeType: failedUpload.mimeType)
    }
}

struct AttachmentRow: View {
    let attachment: TicketAttachment

    @State private var isPreviewPresented = false

    var body: some View {
        Button {
            isPreviewPresented = true
        } label: {
            rowContent
        }
        .buttonStyle(.plain)
        .sheet(isPresented: $isPreviewPresented) {
            NavigationStack {
                AttachmentPreviewView(attachment: attachment)
            }
        }
    }

    private var rowContent: some View {
        HStack {
            AttachmentThumbnailView(attachment: attachment)

            VStack(alignment: .leading) {
                Text(attachment.filename ?? "Attachment")
                if let size = attachment.fileSizeLabel {
                    Text(size)
                        .font(.caption)
                        .foregroundStyle(.secondary)
                }
            }
            Spacer()
            Text(actionLabel)
                .font(.caption.weight(.semibold))
                .foregroundStyle(.blue)
        }
        .contentShape(Rectangle())
    }

    private var actionLabel: String {
        attachment.canPreview == true ? "Preview" : "Open"
    }
}

private struct PendingAttachmentUpload: Identifiable {
    let id = UUID()
    let data: Data
    let filename: String
    let mimeType: String
}

private struct AttachmentThumbnailView: View {
    @Environment(AppSession.self) private var session

    let attachment: TicketAttachment

    @State private var image: UIImage?
    @State private var didAttemptLoad = false

    var body: some View {
        Group {
            if let image {
                Image(uiImage: image)
                    .resizable()
                    .scaledToFill()
            } else {
                Image(systemName: fallbackIcon)
                    .font(.title3)
                    .foregroundStyle(.blue)
                    .frame(maxWidth: .infinity, maxHeight: .infinity)
                    .background(Color.blue.opacity(0.10))
            }
        }
        .frame(width: 44, height: 44)
        .clipShape(RoundedRectangle(cornerRadius: 10, style: .continuous))
        .overlay(
            RoundedRectangle(cornerRadius: 10, style: .continuous)
                .stroke(Color.secondary.opacity(0.16), lineWidth: 1)
        )
        .task(id: attachment.id) {
            await loadThumbnailIfNeeded()
        }
    }

    private var fallbackIcon: String {
        isLikelyImage(attachment) ? "photo" : "paperclip"
    }

    private func loadThumbnailIfNeeded() async {
        guard !didAttemptLoad, isLikelyImage(attachment) else { return }
        didAttemptLoad = true

        do {
            let data = try await session.authenticated { accessToken in
                let resolved = try await resolvedAttachment(accessToken: accessToken)
                let urlString = resolved.previewUrl ?? resolved.downloadUrl
                guard let url = session.client.resourceURL(from: urlString) else {
                    throw FoxDeskAPIError.server(
                        statusCode: 404,
                        message: "This attachment does not have an available preview."
                    )
                }
                return try await session.client.downloadResource(accessToken: accessToken, url: url)
            }

            image = UIImage(data: data)
        } catch {
            image = nil
        }
    }

    private func resolvedAttachment(accessToken: String) async throws -> TicketAttachment {
        if attachment.downloadUrl != nil || attachment.previewUrl != nil {
            return attachment
        }

        return try await session.client.attachmentMetadata(
            accessToken: accessToken,
            attachmentId: attachment.id
        ).data.attachment
    }

    private func isLikelyImage(_ attachment: TicketAttachment) -> Bool {
        if let mimeType = attachment.mimeType?.lowercased(), mimeType.hasPrefix("image/") {
            return true
        }

        guard let filename = attachment.filename?.lowercased() else {
            return attachment.canPreview == true
        }

        return ["jpg", "jpeg", "png", "gif", "webp", "heic", "heif"].contains { ext in
            filename.hasSuffix(".\(ext)")
        }
    }
}
