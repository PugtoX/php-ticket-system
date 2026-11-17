INSERT INTO tickets (subject, body, customer_email, status) VALUES
('Order not delivered', 'Paid on 12 Nov, still no pickup notice.', 'customer1@example.com', 'open'),
('Wrong item in pickup', 'Received size M instead of L.', 'customer2@example.com', 'in_progress'),
('Refund request', 'Cancelled within 24h, refund not received.', 'customer3@example.com', 'closed'),
('Late pickup slot', 'Slot moved twice without notice.', 'customer4@example.com', 'open');
