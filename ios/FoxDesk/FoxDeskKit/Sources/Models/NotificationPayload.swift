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
        let candidate: Int?
        if let number = value as? NSNumber {
            candidate = number.intValue
        } else if let int = value as? Int {
            candidate = int
        } else if let string = value as? String {
            candidate = Int(string.trimmingCharacters(in: .whitespacesAndNewlines))
        } else {
            candidate = nil
        }

        guard let candidate, candidate > 0 else {
            return nil
        }
        return candidate
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
