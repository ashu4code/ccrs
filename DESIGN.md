Act as a frontend engineer. Write a blade view component using Tailwind CSS that showcases a structural horizontal stepper pipeline tracking an electricity complaint ticket.

The stepper should dynamically switch states based on two variables provided by the Laravel backend: $complaint->status and $complaint->current_level.

Render 4 logical points on the timeline stepper:
1. Complaint Lodged (Green if submitted/beyond)
2. Under Regional Manager Review (Green if forwarded/beyond, Amber if active here)
3. Escalated to Competent Authority (Green if resolved, Amber if active here, Gray if not reached)
4. Redressal Resolved (Green if resolved, Gray otherwise)

Below the stepper, format a clean UI vertical timeline card listing the historical loop of $complaint->actions, showing the timestamp, who performed the action (Manager vs Authority), and their written remarks. Keep the interface clean, modern, and accessible.

follow https://sahyog.bihar.gov.in/Sahyog/LoginAdm.aspx