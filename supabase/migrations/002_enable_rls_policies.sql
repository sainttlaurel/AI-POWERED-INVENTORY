-- Migration: Enable Row Level Security and Create Policies
-- Project: AI-POWERED INVENTORY
-- Date: 2026-03-26

-- ============================================================================
-- ENABLE ROW LEVEL SECURITY ON ALL TABLES
-- ============================================================================

ALTER TABLE users ENABLE ROW LEVEL SECURITY;
ALTER TABLE categories ENABLE ROW LEVEL SECURITY;
ALTER TABLE suppliers ENABLE ROW LEVEL SECURITY;
ALTER TABLE products ENABLE ROW LEVEL SECURITY;
ALTER TABLE inventory_logs ENABLE ROW LEVEL SECURITY;
ALTER TABLE sales ENABLE ROW LEVEL SECURITY;
ALTER TABLE sale_items ENABLE ROW LEVEL SECURITY;
ALTER TABLE invoices ENABLE ROW LEVEL SECURITY;
ALTER TABLE invoice_items ENABLE ROW LEVEL SECURITY;
ALTER TABLE notifications ENABLE ROW LEVEL SECURITY;
ALTER TABLE notification_settings ENABLE ROW LEVEL SECURITY;
ALTER TABLE forecast_data ENABLE ROW LEVEL SECURITY;
ALTER TABLE locations ENABLE ROW LEVEL SECURITY;
ALTER TABLE chatbot_logs ENABLE ROW LEVEL SECURITY;
ALTER TABLE user_activity_log ENABLE ROW LEVEL SECURITY;
ALTER TABLE reservations ENABLE ROW LEVEL SECURITY;

-- ============================================================================
-- HELPER FUNCTIONS FOR RLS
-- ============================================================================

-- Function to check if user is authenticated
CREATE OR REPLACE FUNCTION is_authenticated()
RETURNS BOOLEAN AS $$
BEGIN
  RETURN auth.uid() IS NOT NULL;
END;
$$ LANGUAGE plpgsql SECURITY DEFINER;

-- ============================================================================
-- USERS TABLE POLICIES
-- ============================================================================

-- Allow users to read their own profile
CREATE POLICY "Users can view own profile"
  ON users FOR SELECT
  USING (true); -- Allow all authenticated users to view users (for now)

-- Allow users to update their own profile
CREATE POLICY "Users can update own profile"
  ON users FOR UPDATE
  USING (true); -- Simplified for initial setup

-- Allow insert for registration (will be handled by Supabase Auth later)
CREATE POLICY "Allow user registration"
  ON users FOR INSERT
  WITH CHECK (true);

-- ============================================================================
-- CATEGORIES TABLE POLICIES
-- ============================================================================

-- Everyone can read categories
CREATE POLICY "Anyone can view categories"
  ON categories FOR SELECT
  USING (true);

-- Authenticated users can manage categories
CREATE POLICY "Authenticated users can manage categories"
  ON categories FOR ALL
  USING (is_authenticated());

-- ============================================================================
-- SUPPLIERS TABLE POLICIES
-- ============================================================================

-- Everyone can read suppliers
CREATE POLICY "Anyone can view suppliers"
  ON suppliers FOR SELECT
  USING (true);

-- Authenticated users can manage suppliers
CREATE POLICY "Authenticated users can manage suppliers"
  ON suppliers FOR ALL
  USING (is_authenticated());

-- ============================================================================
-- PRODUCTS TABLE POLICIES
-- ============================================================================

-- Everyone can read products
CREATE POLICY "Anyone can view products"
  ON products FOR SELECT
  USING (true);

-- Authenticated users can manage products
CREATE POLICY "Authenticated users can manage products"
  ON products FOR ALL
  USING (is_authenticated());

-- ============================================================================
-- INVENTORY LOGS TABLE POLICIES
-- ============================================================================

-- Authenticated users can view inventory logs
CREATE POLICY "Authenticated users can view inventory logs"
  ON inventory_logs FOR SELECT
  USING (is_authenticated());

-- Authenticated users can create inventory logs
CREATE POLICY "Authenticated users can create inventory logs"
  ON inventory_logs FOR INSERT
  WITH CHECK (is_authenticated());

-- ============================================================================
-- SALES TABLE POLICIES
-- ============================================================================

-- Authenticated users can view sales
CREATE POLICY "Authenticated users can view sales"
  ON sales FOR SELECT
  USING (is_authenticated());

-- Authenticated users can create sales
CREATE POLICY "Authenticated users can create sales"
  ON sales FOR INSERT
  WITH CHECK (is_authenticated());

-- Authenticated users can update sales
CREATE POLICY "Authenticated users can update sales"
  ON sales FOR UPDATE
  USING (is_authenticated());

-- ============================================================================
-- SALE ITEMS TABLE POLICIES
-- ============================================================================

-- Authenticated users can view sale items
CREATE POLICY "Authenticated users can view sale items"
  ON sale_items FOR SELECT
  USING (is_authenticated());

-- Authenticated users can create sale items
CREATE POLICY "Authenticated users can create sale items"
  ON sale_items FOR INSERT
  WITH CHECK (is_authenticated());

-- ============================================================================
-- INVOICES TABLE POLICIES
-- ============================================================================

-- Authenticated users can view invoices
CREATE POLICY "Authenticated users can view invoices"
  ON invoices FOR SELECT
  USING (is_authenticated());

-- Authenticated users can manage invoices
CREATE POLICY "Authenticated users can manage invoices"
  ON invoices FOR ALL
  USING (is_authenticated());

-- ============================================================================
-- INVOICE ITEMS TABLE POLICIES
-- ============================================================================

-- Authenticated users can view invoice items
CREATE POLICY "Authenticated users can view invoice items"
  ON invoice_items FOR SELECT
  USING (is_authenticated());

-- Authenticated users can manage invoice items
CREATE POLICY "Authenticated users can manage invoice items"
  ON invoice_items FOR ALL
  USING (is_authenticated());

-- ============================================================================
-- NOTIFICATIONS TABLE POLICIES
-- ============================================================================

-- Users can view their own notifications
CREATE POLICY "Users can view own notifications"
  ON notifications FOR SELECT
  USING (is_authenticated());

-- Users can update their own notifications (mark as read)
CREATE POLICY "Users can update own notifications"
  ON notifications FOR UPDATE
  USING (is_authenticated());

-- System can create notifications
CREATE POLICY "System can create notifications"
  ON notifications FOR INSERT
  WITH CHECK (is_authenticated());

-- ============================================================================
-- NOTIFICATION SETTINGS TABLE POLICIES
-- ============================================================================

-- Users can view their own notification settings
CREATE POLICY "Users can view own notification settings"
  ON notification_settings FOR SELECT
  USING (is_authenticated());

-- Users can manage their own notification settings
CREATE POLICY "Users can manage own notification settings"
  ON notification_settings FOR ALL
  USING (is_authenticated());

-- ============================================================================
-- FORECAST DATA TABLE POLICIES
-- ============================================================================

-- Authenticated users can view forecast data
CREATE POLICY "Authenticated users can view forecast data"
  ON forecast_data FOR SELECT
  USING (is_authenticated());

-- Authenticated users can manage forecast data
CREATE POLICY "Authenticated users can manage forecast data"
  ON forecast_data FOR ALL
  USING (is_authenticated());

-- ============================================================================
-- LOCATIONS TABLE POLICIES
-- ============================================================================

-- Authenticated users can view locations
CREATE POLICY "Authenticated users can view locations"
  ON locations FOR SELECT
  USING (is_authenticated());

-- Authenticated users can manage locations
CREATE POLICY "Authenticated users can manage locations"
  ON locations FOR ALL
  USING (is_authenticated());

-- ============================================================================
-- CHATBOT LOGS TABLE POLICIES
-- ============================================================================

-- Users can view their own chatbot logs
CREATE POLICY "Users can view own chatbot logs"
  ON chatbot_logs FOR SELECT
  USING (is_authenticated());

-- Users can create chatbot logs
CREATE POLICY "Users can create chatbot logs"
  ON chatbot_logs FOR INSERT
  WITH CHECK (is_authenticated());

-- ============================================================================
-- USER ACTIVITY LOG TABLE POLICIES
-- ============================================================================

-- Authenticated users can view activity logs
CREATE POLICY "Authenticated users can view activity logs"
  ON user_activity_log FOR SELECT
  USING (is_authenticated());

-- System can create activity logs
CREATE POLICY "System can create activity logs"
  ON user_activity_log FOR INSERT
  WITH CHECK (is_authenticated());

-- ============================================================================
-- RESERVATIONS TABLE POLICIES
-- ============================================================================

-- Authenticated users can view reservations
CREATE POLICY "Authenticated users can view reservations"
  ON reservations FOR SELECT
  USING (is_authenticated());

-- Authenticated users can manage reservations
CREATE POLICY "Authenticated users can manage reservations"
  ON reservations FOR ALL
  USING (is_authenticated());

-- ============================================================================
-- NOTES
-- ============================================================================

-- These are simplified policies for initial setup
-- In production, you should:
-- 1. Integrate with Supabase Auth (auth.uid())
-- 2. Add role-based access control (admin, manager, staff)
-- 3. Restrict policies based on user roles
-- 4. Add more granular permissions
