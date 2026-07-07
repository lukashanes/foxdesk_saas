import XCTest
@testable import FoxDeskKit

final class MobileRichTextFormatterTests: XCTestCase {
    func testConvertsParagraphsListsAndInlineFormattingToHTML() {
        let html = MobileRichTextFormatter.html(from: """
        First paragraph
        second line

        - one
        - **two**

        1. first
        2. _second_
        """)

        XCTAssertEqual(
            html,
            "<p>First paragraph<br>second line</p><ul><li>one</li><li><strong>two</strong></li></ul><ol><li>first</li><li><em>second</em></li></ol>"
        )
    }

    func testEscapesHTMLBeforeApplyingAllowedInlineFormatting() {
        let html = MobileRichTextFormatter.html(from: """
        <script>alert("x")</script>
        **safe**
        """)

        XCTAssertEqual(
            html,
            "<p>&lt;script&gt;alert(\"x\")&lt;/script&gt;<br><strong>safe</strong></p>"
        )
        XCTAssertFalse(html.contains("<script>"))
    }

    func testNormalizesWindowsLineEndingsAndSkipsEmptyBlocks() {
        let html = MobileRichTextFormatter.html(from: "Hello\r\n\r\n* item\r\n")

        XCTAssertEqual(html, "<p>Hello</p><ul><li>item</li></ul>")
    }
}
