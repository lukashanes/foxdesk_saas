import XCTest
@testable import FoxDeskKit

final class MobileRichTextRendererTests: XCTestCase {
    func testPreservesParagraphsOrderedListsAndInlineFormatting() {
        let markdown = MobileRichTextRenderer.markdown(fromHTML: """
        <p>Hello <strong>team</strong>.</p>
        <ol><li>Open the <em>ticket</em></li><li>Reply</li></ol>
        """)

        XCTAssertEqual(markdown, "Hello **team**.\n\n1. Open the *ticket*\n2. Reply")
        XCTAssertNotNil(MobileRichTextRenderer.attributedString(fromHTML: "<p><strong>Saved</strong></p>"))
    }

    func testKeepsSafeLinksAndDoesNotLoadRemoteImages() {
        let markdown = MobileRichTextRenderer.markdown(fromHTML: """
        <p><a href="https://foxdesk.net/help">Open help</a></p>
        <img src="https://tracker.example/pixel.gif" alt="Screenshot">
        <script>steal()</script>
        """)

        XCTAssertTrue(markdown.contains("[Open help](https://foxdesk.net/help)"))
        XCTAssertTrue(markdown.contains("[Image: Screenshot]"))
        XCTAssertFalse(markdown.contains("tracker.example"))
        XCTAssertFalse(markdown.contains("steal"))
    }

    func testDropsUnsafeLinkTargetsButKeepsVisibleLabel() {
        let markdown = MobileRichTextRenderer.markdown(
            fromHTML: #"<p><a href="javascript:alert(1)">Customer link</a> &#128640;</p>"#
        )

        XCTAssertEqual(markdown, "Customer link 🚀")
        XCTAssertFalse(markdown.contains("javascript:"))
    }
}
