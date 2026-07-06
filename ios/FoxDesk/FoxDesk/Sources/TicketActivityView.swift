import SwiftUI
import FoxDeskKit

struct TicketActivitySections: View {
    let comments: [TicketComment]
    let timeEntries: [TicketTimeEntry]

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
                        linkedTimeEntries: timeEntriesByComment[comment.id] ?? []
                    )
                }
            }
        }

        if !orphanTimeEntries.isEmpty {
            Section("Time") {
                ForEach(orphanTimeEntries) { entry in
                    TicketTimeEntryRow(entry: entry)
                }
            }
        }
    }
}

private struct TicketCommentRow: View {
    let comment: TicketComment
    let linkedTimeEntries: [TicketTimeEntry]

    var body: some View {
        VStack(alignment: .leading, spacing: 8) {
            HStack {
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
            }

            RichCommentText(html: comment.contentHtml, fallback: comment.contentText)

            if !linkedTimeEntries.isEmpty {
                VStack(alignment: .leading, spacing: 6) {
                    ForEach(linkedTimeEntries) { entry in
                        LinkedCommentTimeRow(entry: entry)
                    }
                }
                .padding(.top, 2)
            }
        }
        .padding(.vertical, 4)
    }
}

private struct LinkedCommentTimeRow: View {
    let entry: TicketTimeEntry

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
        }
        .font(.caption)
        .foregroundStyle(.blue)
        .padding(.horizontal, 10)
        .padding(.vertical, 6)
        .background(Color.blue.opacity(0.10), in: Capsule())
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
        let markdown = markdownFromHTML(source)
        return try? AttributedString(markdown: markdown)
    }

    private func markdownFromHTML(_ html: String) -> String {
        var text = html
        let replacements: [(String, String)] = [
            ("<br>", "\n"),
            ("<br/>", "\n"),
            ("<br />", "\n"),
            ("<p>", "\n\n"),
            ("</p>", "\n\n"),
            ("<ul>", "\n"),
            ("</ul>", "\n"),
            ("<ol>", "\n"),
            ("</ol>", "\n"),
            ("<li>", "- "),
            ("</li>", "\n"),
            ("<strong>", "**"),
            ("</strong>", "**"),
            ("<b>", "**"),
            ("</b>", "**"),
            ("<em>", "*"),
            ("</em>", "*"),
            ("<i>", "*"),
            ("</i>", "*")
        ]
        for (needle, replacement) in replacements {
            text = text.replacingOccurrences(of: needle, with: replacement, options: [.caseInsensitive])
        }
        text = text.replacingOccurrences(of: #"(?s)<[^>]+>"#, with: "", options: .regularExpression)
        return decodeBasicHTMLEntities(text)
            .components(separatedBy: .newlines)
            .map { $0.trimmingCharacters(in: .whitespaces) }
            .joined(separator: "\n")
            .trimmingCharacters(in: .whitespacesAndNewlines)
    }

    private func decodeBasicHTMLEntities(_ value: String) -> String {
        value
            .replacingOccurrences(of: "&nbsp;", with: " ")
            .replacingOccurrences(of: "&amp;", with: "&")
            .replacingOccurrences(of: "&lt;", with: "<")
            .replacingOccurrences(of: "&gt;", with: ">")
            .replacingOccurrences(of: "&quot;", with: "\"")
            .replacingOccurrences(of: "&#39;", with: "'")
    }
}

private struct TicketTimeEntryRow: View {
    let entry: TicketTimeEntry

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
        }
    }
}
