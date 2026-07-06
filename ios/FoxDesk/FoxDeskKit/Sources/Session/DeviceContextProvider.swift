import Foundation
import UIKit

public enum DeviceContextProvider {
    public static func current(appVersion: String) -> DeviceContext {
        let device = UIDevice.current
        return DeviceContext(
            deviceId: device.identifierForVendor?.uuidString ?? UUID().uuidString,
            deviceName: device.name,
            appVersion: appVersion
        )
    }
}

