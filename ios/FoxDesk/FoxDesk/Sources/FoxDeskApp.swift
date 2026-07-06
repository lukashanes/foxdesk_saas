import SwiftUI
import FoxDeskKit

@main
struct FoxDeskApp: App {
    @UIApplicationDelegateAdaptor(FoxDeskAppDelegate.self) private var appDelegate

    @State private var session: AppSession
    @State private var pushRegistration = PushRegistrationService()
    @State private var pushRouter = PushNavigationRouter()

    init() {
        let client = FoxDeskAPIClient(environment: AppConfiguration.environment)
        let appVersion = Bundle.main.infoDictionary?["CFBundleShortVersionString"] as? String ?? "0.1.0"
        _session = State(initialValue: AppSession(
            client: client,
            device: DeviceContextProvider.current(appVersion: appVersion)
        ))
    }

    var body: some Scene {
        WindowGroup {
            #if DEBUG
            if ScreenshotDemoConfiguration.isEnabled {
                ScreenshotDemoRootView()
            } else {
                RootView()
                    .environment(session)
                    .environment(pushRegistration)
                    .environment(pushRouter)
            }
            #else
            RootView()
                .environment(session)
                .environment(pushRegistration)
                .environment(pushRouter)
            #endif
        }
    }
}

private enum AppConfiguration {
    static var environment: FoxDeskEnvironment {
        let configured = Bundle.main.object(forInfoDictionaryKey: "FOXDESK_API_BASE_URL") as? String
        let rawURL = configured?.isEmpty == false ? configured! : "https://app.foxdesk.net/index.php"
        return FoxDeskEnvironment(baseURL: URL(string: rawURL) ?? FoxDeskEnvironment.production.baseURL)
    }
}
