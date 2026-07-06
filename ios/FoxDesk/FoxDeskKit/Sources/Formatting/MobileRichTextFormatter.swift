import Foundation

public enum MobileRichTextFormatter {
    public static func html(from text: String) -> String {
        let lines = text.replacingOccurrences(of: "\r\n", with: "\n")
            .replacingOccurrences(of: "\r", with: "\n")
            .components(separatedBy: "\n")

        var blocks: [String] = []
        var paragraphLines: [String] = []
        var unorderedItems: [String] = []
        var orderedItems: [String] = []

        func flushParagraph() {
            guard !paragraphLines.isEmpty else { return }
            let body = paragraphLines
                .map { formatInline(escape($0.trimmingCharacters(in: .whitespacesAndNewlines))) }
                .joined(separator: "<br>")
            blocks.append("<p>\(body)</p>")
            paragraphLines.removeAll()
        }

        func flushUnorderedList() {
            guard !unorderedItems.isEmpty else { return }
            blocks.append("<ul>" + unorderedItems.map { "<li>\($0)</li>" }.joined() + "</ul>")
            unorderedItems.removeAll()
        }

        func flushOrderedList() {
            guard !orderedItems.isEmpty else { return }
            blocks.append("<ol>" + orderedItems.map { "<li>\($0)</li>" }.joined() + "</ol>")
            orderedItems.removeAll()
        }

        func flushLists() {
            flushUnorderedList()
            flushOrderedList()
        }

        for rawLine in lines {
            let line = rawLine.trimmingCharacters(in: .whitespacesAndNewlines)
            if line.isEmpty {
                flushParagraph()
                flushLists()
                continue
            }

            if let item = unorderedListItem(from: line) {
                flushParagraph()
                flushOrderedList()
                unorderedItems.append(formatInline(escape(item)))
                continue
            }

            if let item = orderedListItem(from: line) {
                flushParagraph()
                flushUnorderedList()
                orderedItems.append(formatInline(escape(item)))
                continue
            }

            flushLists()
            paragraphLines.append(line)
        }

        flushParagraph()
        flushLists()

        return blocks.joined()
    }

    private static func unorderedListItem(from line: String) -> String? {
        for marker in ["- ", "* ", "• "] where line.hasPrefix(marker) {
            return String(line.dropFirst(marker.count)).trimmingCharacters(in: .whitespacesAndNewlines)
        }
        return nil
    }

    private static func orderedListItem(from line: String) -> String? {
        guard let match = line.range(of: #"^\d+[\.)]\s+"#, options: .regularExpression) else {
            return nil
        }
        return String(line[match.upperBound...]).trimmingCharacters(in: .whitespacesAndNewlines)
    }

    private static func escape(_ value: String) -> String {
        value
            .replacingOccurrences(of: "&", with: "&amp;")
            .replacingOccurrences(of: "<", with: "&lt;")
            .replacingOccurrences(of: ">", with: "&gt;")
    }

    private static func formatInline(_ value: String) -> String {
        var formatted = replace(pattern: #"(?s)\*\*([^*]+)\*\*"#, in: value, with: "<strong>$1</strong>")
        formatted = replace(pattern: #"(?s)__([^_]+)__"#, in: formatted, with: "<strong>$1</strong>")
        formatted = replace(pattern: #"(?s)(?<!\*)\*([^*\n]+)\*(?!\*)"#, in: formatted, with: "<em>$1</em>")
        formatted = replace(pattern: #"(?s)(?<!_)_([^_\n]+)_(?!_)"#, in: formatted, with: "<em>$1</em>")
        return formatted
    }

    private static func replace(pattern: String, in value: String, with template: String) -> String {
        guard let expression = try? NSRegularExpression(pattern: pattern) else {
            return value
        }
        let range = NSRange(value.startIndex..<value.endIndex, in: value)
        return expression.stringByReplacingMatches(in: value, range: range, withTemplate: template)
    }
}
