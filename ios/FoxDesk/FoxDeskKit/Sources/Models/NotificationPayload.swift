import Foundation

public enum FoxDeskNotificationPayload {
    public static func ticketID(from userInfo: [AnyHashable: Any]) -> Int? {
        if let ticketID = ticketID(in: userInfo) {
            return ticketID
        }

        guard let data = dictionaryValue(userInfo[AnyHashable("data")]) else {
            return nil
        }

        return ticketID(in: data)
    }

    private static func ticketID(in dictionary: [AnyHashable: Any]) -> Int? {
        for key in ["ticket_id", "ticketId", "ticketID"] {
            if let value = intValue(dictionary[AnyHashable(key)]) {
                return value
            }
        }

        return nil
    }

    private static func intValue(_ value: Any?) -> Int? {
        if let number = value as? NSNumber {
            return number.intValue
        }

        if let int = value as? Int {
            return int
        }

        if let string = value as? String {
            return Int(string.trimmingCharacters(in: .whitespacesAndNewlines))
        }

        return nil
    }

    private static func dictionaryValue(_ value: Any?) -> [AnyHashable: Any]? {
        if let dictionary = value as? [AnyHashable: Any] {
            return dictionary
        }

        if let dictionary = value as? [String: Any] {
            return Dictionary(uniqueKeysWithValues: dictionary.map { key, value in
                (AnyHashable(key), value)
            })
        }

        return nil
    }
}
