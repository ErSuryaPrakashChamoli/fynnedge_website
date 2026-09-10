---
paths:
  - 'app/Modules/Newsletter/**,app/Mail/Newsletter/**,app/Http/Controllers/NewsletterController.php'
---

# Http Controllers

## Newsletter is a standalone module — never joined to leads, customers or the funnel
NewsletterSubscriber has NO relation to Customer, JourneySession or Application, and no lead_id/lms_id/fynnon_id column. Someone who wants monthly finance tips has not applied for anything; joining the two turns a mailing list into a lead database and breaks the consent basis it was collected under. The only outside relations a campaign has are Article (the blog post its body was generated from) and User (its author). Future FYNN-ON integration goes through an API/webhook against this module, not a foreign key into it.

Load-bearing invariants:
- NewsletterSubscriber::scopeMailable() is the ONLY definition of "may receive a campaign". Every send path goes through it; segments narrow it and can never widen it.
- SendNewsletterEmail re-checks isMailable() at send time, not just when the audience was built — a big campaign sits in the queue for minutes and someone can unsubscribe in that window.
- Recipient rows are inserted under a unique (campaign, subscriber) index BEFORE any mail is queued; firstOrCreate + wasRecentlyCreated is what makes a retried or re-dispatched campaign job unable to mail anyone twice.
- Tokens (confirmation, unsubscribe, tracking) are stored SHA-256 hashed. Plaintext exists only in the moment it is generated and mailed, so mailables take it as a constructor argument rather than reading it off the model.
- The public subscribe endpoint must stay neutral: identical status code AND wording whether or not the address is already on the list, or it becomes a subscriber-enumeration oracle. Never re-mail an already-active subscriber from it.
- Unsubscribing never deletes the row; the row IS the record of "do not mail this address".
