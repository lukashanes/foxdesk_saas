import QuickLook
import SwiftUI
import FoxDeskKit

struct AttachmentPreviewView: View {
    @Environment(AppSession.self) private var session

    let attachment: TicketAttachment

    @State private var state: PreviewState = .loading
    @State private var downloadedFileURL: URL?

    var body: some View {
        Group {
            switch state {
            case .loading:
                ProgressView("Loading attachment")
                    .frame(maxWidth: .infinity, maxHeight: .infinity)
            case .file(let url):
                QuickLookPreview(url: url)
                    .ignoresSafeArea(edges: .bottom)
            case .failed(let message):
                ContentUnavailableView("Could not open attachment", systemImage: "paperclip.badge.exclamationmark", description: Text(message))
            }
        }
        .navigationTitle(attachment.filename?.isEmpty == false ? attachment.filename ?? "Attachment" : "Attachment")
        .navigationBarTitleDisplayMode(.inline)
        .task(id: attachment.id) {
            await loadPreview()
        }
        .onDisappear {
            cleanupDownloadedFile()
        }
    }

    private func loadPreview() async {
        state = .loading
        cleanupDownloadedFile()

        do {
            let fileURL = try await session.authenticated { accessToken in
                let resolved = try await resolvedAttachment(accessToken: accessToken)
                let urlString = resolved.canPreview == true
                    ? (resolved.previewUrl ?? resolved.downloadUrl)
                    : (resolved.downloadUrl ?? resolved.previewUrl)

                guard let url = session.client.resourceURL(from: urlString) else {
                    throw FoxDeskAPIError.server(
                        statusCode: 404,
                        message: "This attachment does not have an available preview."
                    )
                }

                return try await session.client.downloadResourceToTemporaryFile(
                    accessToken: accessToken,
                    url: url,
                    suggestedFilename: resolved.filename
                )
            }
            downloadedFileURL = fileURL
            state = .file(fileURL)
        } catch {
            state = .failed(error.localizedDescription)
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

    private func cleanupDownloadedFile() {
        guard let downloadedFileURL else { return }
        try? FileManager.default.removeItem(at: downloadedFileURL)
        self.downloadedFileURL = nil
    }
}

private enum PreviewState {
    case loading
    case file(URL)
    case failed(String)
}

private struct QuickLookPreview: UIViewControllerRepresentable {
    let url: URL

    func makeUIViewController(context: Context) -> QLPreviewController {
        let controller = QLPreviewController()
        controller.dataSource = context.coordinator
        return controller
    }

    func updateUIViewController(_ controller: QLPreviewController, context: Context) {
        context.coordinator.url = url
        controller.reloadData()
    }

    func makeCoordinator() -> Coordinator {
        Coordinator(url: url)
    }

    final class Coordinator: NSObject, QLPreviewControllerDataSource {
        var url: URL

        init(url: URL) {
            self.url = url
        }

        func numberOfPreviewItems(in controller: QLPreviewController) -> Int {
            1
        }

        func previewController(_ controller: QLPreviewController, previewItemAt index: Int) -> QLPreviewItem {
            url as NSURL
        }
    }
}
