import Foundation

public enum MobileRichTextRenderer {
    public static func attributedString(fromHTML html: String) -> AttributedString? {
        let markdown = markdown(fromHTML: html)
        guard !markdown.isEmpty else { return nil }
        return try? AttributedString(markdown: markdown)
    }

    public static func markdown(fromHTML html: String) -> String {
        var text = html
        text = replacing(pattern: #"(?is)<(script|style|head)\b[^>]*>.*?</\1>"#, in: text) { _, _ in "" }
        text = replacing(pattern: #"(?is)<a\b[^>]*\bhref\s*=\s*[\"']([^\"']+)[\"'][^>]*>(.*?)</a>"#, in: text) { match, source in
            let href = decodedEntities(capture(match, group: 1, in: source))
            let label = inlineMarkdown(capture(match, group: 2, in: source))
            guard isSafeLink(href), !label.isEmpty else { return label }
            return "[\(escapeMarkdownLabel(label))](\(href))"
        }
        text = replacing(pattern: #"(?is)<img\b[^>]*\balt\s*=\s*[\"']([^\"']*)[\"'][^>]*>"#, in: text) { match, source in
            let alt = decodedEntities(capture(match, group: 1, in: source)).trimmingCharacters(in: .whitespacesAndNewlines)
            return alt.isEmpty ? "" : "[Image: \(alt)]"
        }
        text = replacing(pattern: #"(?is)<img\b[^>]*>"#, in: text) { _, _ in "" }
        text = replacingListBlocks(in: text, tag: "ol", marker: { index in "\(index + 1). " })
        text = replacingListBlocks(in: text, tag: "ul", marker: { _ in "- " })
        text = replacing(pattern: #"(?is)<h[1-6]\b[^>]*>(.*?)</h[1-6]>"#, in: text) { match, source in
            let value = inlineMarkdown(capture(match, group: 1, in: source))
            return value.isEmpty ? "" : "\n\n**\(value)**\n\n"
        }
        text = replacing(pattern: #"(?is)<blockquote\b[^>]*>(.*?)</blockquote>"#, in: text) { match, source in
            inlineMarkdown(capture(match, group: 1, in: source))
                .split(separator: "\n", omittingEmptySubsequences: false)
                .map { "> \($0)" }
                .joined(separator: "\n")
        }

        let structuralReplacements: [(String, String)] = [
            (#"(?i)<br\s*/?>"#, "\n"),
            (#"(?i)</?(p|div|section|article)\b[^>]*>"#, "\n\n"),
            (#"(?i)</?(strong|b)\b[^>]*>"#, "**"),
            (#"(?i)</?(em|i)\b[^>]*>"#, "*"),
            (#"(?i)</?(del|s|strike)\b[^>]*>"#, "~~"),
            (#"(?i)</?code\b[^>]*>"#, "`")
        ]
        for (pattern, replacement) in structuralReplacements {
            text = text.replacingOccurrences(of: pattern, with: replacement, options: .regularExpression)
        }

        text = text.replacingOccurrences(of: #"(?is)<[^>]+>"#, with: "", options: .regularExpression)
        text = decodedEntities(text)
        text = text.replacingOccurrences(of: #"[ \t]+\n"#, with: "\n", options: .regularExpression)
        text = text.replacingOccurrences(of: #"\n[ \t]+"#, with: "\n", options: .regularExpression)
        text = text.replacingOccurrences(of: #"\n{3,}"#, with: "\n\n", options: .regularExpression)
        return text.trimmingCharacters(in: .whitespacesAndNewlines)
    }

    private static func replacingListBlocks(
        in value: String,
        tag: String,
        marker: (Int) -> String
    ) -> String {
        replacing(pattern: "(?is)<\(tag)\\b[^>]*>(.*?)</\(tag)>", in: value) { match, source in
            let body = capture(match, group: 1, in: source)
            guard let itemExpression = try? NSRegularExpression(pattern: #"(?is)<li\b[^>]*>(.*?)</li>"#) else {
                return inlineMarkdown(body)
            }
            let range = NSRange(body.startIndex..<body.endIndex, in: body)
            let items = itemExpression.matches(in: body, range: range).enumerated().compactMap { index, item -> String? in
                let content = inlineMarkdown(capture(item, group: 1, in: body))
                return content.isEmpty ? nil : marker(index) + content
            }
            return items.isEmpty ? inlineMarkdown(body) : "\n" + items.joined(separator: "\n") + "\n"
        }
    }

    private static func inlineMarkdown(_ html: String) -> String {
        var value = html
        let replacements: [(String, String)] = [
            (#"(?i)<br\s*/?>"#, "\n"),
            (#"(?i)</?(strong|b)\b[^>]*>"#, "**"),
            (#"(?i)</?(em|i)\b[^>]*>"#, "*"),
            (#"(?i)</?(del|s|strike)\b[^>]*>"#, "~~"),
            (#"(?i)</?code\b[^>]*>"#, "`")
        ]
        for (pattern, replacement) in replacements {
            value = value.replacingOccurrences(of: pattern, with: replacement, options: .regularExpression)
        }
        value = value.replacingOccurrences(of: #"(?is)<[^>]+>"#, with: "", options: .regularExpression)
        return decodedEntities(value).trimmingCharacters(in: .whitespacesAndNewlines)
    }

    private static func isSafeLink(_ value: String) -> Bool {
        guard let components = URLComponents(string: value), let scheme = components.scheme?.lowercased() else {
            return false
        }
        return ["http", "https", "mailto"].contains(scheme)
    }

    private static func escapeMarkdownLabel(_ value: String) -> String {
        value.replacingOccurrences(of: "[", with: "\\[")
            .replacingOccurrences(of: "]", with: "\\]")
    }

    private static func decodedEntities(_ value: String) -> String {
        var result = value
            .replacingOccurrences(of: "&nbsp;", with: " ", options: .caseInsensitive)
            .replacingOccurrences(of: "&amp;", with: "&", options: .caseInsensitive)
            .replacingOccurrences(of: "&lt;", with: "<", options: .caseInsensitive)
            .replacingOccurrences(of: "&gt;", with: ">", options: .caseInsensitive)
            .replacingOccurrences(of: "&quot;", with: "\"", options: .caseInsensitive)
            .replacingOccurrences(of: "&apos;", with: "'", options: .caseInsensitive)
            .replacingOccurrences(of: "&#39;", with: "'", options: .caseInsensitive)

        result = replacing(pattern: #"&#(\d+);"#, in: result) { match, source in
            scalar(capture(match, group: 1, in: source), radix: 10)
        }
        result = replacing(pattern: #"&#x([0-9A-Fa-f]+);"#, in: result) { match, source in
            scalar(capture(match, group: 1, in: source), radix: 16)
        }
        return result
    }

    private static func scalar(_ value: String, radix: Int) -> String {
        guard let number = UInt32(value, radix: radix), let scalar = UnicodeScalar(number) else {
            return ""
        }
        return String(Character(scalar))
    }

    private static func replacing(
        pattern: String,
        in value: String,
        transform: (NSTextCheckingResult, String) -> String
    ) -> String {
        guard let expression = try? NSRegularExpression(pattern: pattern) else { return value }
        let matches = expression.matches(in: value, range: NSRange(value.startIndex..<value.endIndex, in: value))
        var result = value
        for match in matches.reversed() {
            guard let range = Range(match.range, in: result) else { continue }
            result.replaceSubrange(range, with: transform(match, value))
        }
        return result
    }

    private static func capture(_ match: NSTextCheckingResult, group: Int, in source: String) -> String {
        guard group < match.numberOfRanges, let range = Range(match.range(at: group), in: source) else {
            return ""
        }
        return String(source[range])
    }
}
