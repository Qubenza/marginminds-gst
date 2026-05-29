import React, { useState } from 'react';
import {
  Alert,
  Box,
  Button,
  Checkbox,
  CircularProgress,
  FormControl,
  FormControlLabel,
  FormHelperText,
  Grid,
  InputLabel,
  MenuItem,
  Select,
  Snackbar,
  Stack,
  Tab,
  Tabs,
  TextField,
} from '@mui/material';

const defaultSettings = {
  enable_gst: false,
  business_legal_name: '',
  store_gstin: '',
  business_address: '',
  business_state: '',
  enable_cgst_sgst: false,
  enable_igst: false,
  default_gst_rate: '',
  prices_include_gst: false,
  apply_gst_based_on_shipping_state: false,
  round_tax_amounts: false,
  show_gstin_at_checkout: false,
  gstin_field_required: false,
  save_gstin_to_orders: false,
  display_gst_in_order_emails: false,
  enable_gst_on_shipping: false,
  display_gst_below_product_price: false,
  enable_pdf_invoices: false,
  invoice_prefix: 'INV-',
  show_gstin_on_invoice: false,
  attach_invoice_to_emails: false,
  invoice_footer_text: '',
  show_gst_on_cart: false,
  show_gst_on_checkout: false,
  show_gst_in_order_summary: false,
};

function CustomTabPanel(props) {
  const { children, value, index } = props;
  return (
    <div hidden={value !== index}>
      {value === index && <Box sx={{ p: 2 }}>{children}</Box>}
    </div>
  );
}

function a11yProps(index) {
  return {
    id: `simple-tab-${index}`,
    'aria-controls': `simple-tabpanel-${index}`,
  };
}

const states = [
  { code: 'AP', name: 'Andhra Pradesh' },
  { code: 'AR', name: 'Arunachal Pradesh' },
  { code: 'AS', name: 'Assam' },
  { code: 'BR', name: 'Bihar' },
  { code: 'CT', name: 'Chhattisgarh' },
  { code: 'GA', name: 'Goa' },
  { code: 'GJ', name: 'Gujarat' },
  { code: 'HR', name: 'Haryana' },
  { code: 'HP', name: 'Himachal Pradesh' },
  { code: 'JH', name: 'Jharkhand' },
  { code: 'KA', name: 'Karnataka' },
  { code: 'KL', name: 'Kerala' },
  { code: 'MP', name: 'Madhya Pradesh' },
  { code: 'MH', name: 'Maharashtra' },
  { code: 'MN', name: 'Manipur' },
  { code: 'ML', name: 'Meghalaya' },
  { code: 'MZ', name: 'Mizoram' },
  { code: 'NL', name: 'Nagaland' },
  { code: 'OR', name: 'Odisha' },
  { code: 'PB', name: 'Punjab' },
  { code: 'RJ', name: 'Rajasthan' },
  { code: 'SK', name: 'Sikkim' },
  { code: 'TN', name: 'Tamil Nadu' },
  { code: 'TS', name: 'Telangana' },
  { code: 'TR', name: 'Tripura' },
  { code: 'UP', name: 'Uttar Pradesh' },
  { code: 'UT', name: 'Uttarakhand' },
  { code: 'WB', name: 'West Bengal' },
  { code: 'AN', name: 'Andaman and Nicobar Islands' },
  { code: 'CH', name: 'Chandigarh' },
  { code: 'DN', name: 'Dadra and Nagar Haveli and Daman and Diu' },
  { code: 'DL', name: 'Delhi' },
  { code: 'JK', name: 'Jammu and Kashmir' },
  { code: 'LA', name: 'Ladakh' },
  { code: 'LD', name: 'Lakshadweep' },
  { code: 'PY', name: 'Puducherry' },
];

const gstRates = ['0%', '5%', '12%', '18%', '28%'];

const GSTIN_REGEX = /^[0-9]{2}[A-Z]{5}[0-9]{4}[A-Z]{1}[1-9A-Z]{1}Z[0-9A-Z]{1}$/;

function isValidGstin(value) {
  return !value || GSTIN_REGEX.test(value.trim().toUpperCase());
}

function App({ saveAction, initialSettings }) {
  const MMSGSTAdmin = window.MMSGSTAdmin || {};
  const [tabValue, setTabValue] = useState(0);
  const [settings, setSettings] = useState({ ...defaultSettings, ...(initialSettings || MMSGSTAdmin.settings || {}) });
  const [saving, setSaving] = useState(false);
  const [snackbar, setSnackbar] = useState({ open: false, message: '', severity: 'success' });
  const [gstinError, setGstinError] = useState('');

  const set = (key) => (event) => {
    const value = event.target.type === 'checkbox' ? event.target.checked : event.target.value;
    if (key === 'store_gstin') {
      setGstinError(isValidGstin(value) ? '' : 'Invalid GSTIN format (e.g. 22AAAAA0000A1Z5)');
    }
    setSettings((prev) => ({ ...prev, [key]: value }));
  };

  const handleSave = async () => {
    if (!isValidGstin(settings.store_gstin)) {
      setGstinError('Invalid GSTIN format (e.g. 22AAAAA0000A1Z5)');
      setTabValue(0);
      setSnackbar({ open: true, message: 'Please fix the GSTIN before saving.', severity: 'error' });
      return;
    }
    setSaving(true);
    try {
      const formData = new FormData();
      formData.append('action', saveAction || 'marginminds_gst_save_settings');
      formData.append('nonce', MMSGSTAdmin.nonce || '');
      formData.append('settings', JSON.stringify(settings));
      const response = await fetch(MMSGSTAdmin.ajax_url || '/wp-admin/admin-ajax.php', {
        method: 'POST',
        body: formData,
      });

      const data = await response.json();
      if (data.success) {
        setSnackbar({ open: true, message: data.data?.message || 'Settings saved!', severity: 'success' });
      } else {
        setSnackbar({ open: true, message: data.data?.message || 'Failed to save settings.', severity: 'error' });
      }
    } catch {
      setSnackbar({ open: true, message: 'Network error. Please try again.', severity: 'error' });
    } finally {
      setSaving(false);
    }
  };

  const closeSnackbar = () => setSnackbar((prev) => ({ ...prev, open: false }));

  return (
    <Box sx={{ width: '100%' }}>
      <Box sx={{
        borderBottom: 1,
        borderColor: 'divider',
        display: 'flex',
        alignItems: 'center',
        justifyContent: 'space-between',
        gap: 2,
      }}>
        <Tabs value={tabValue} onChange={(_, v) => setTabValue(v)}>
          <Tab label="General Settings" {...a11yProps(0)} />
          <Tab label="Tax Configuration" {...a11yProps(1)} />
          <Tab label="WooCommerce Integration" {...a11yProps(2)} />
          <Tab label="Invoice Settings" {...a11yProps(3)} />
          <Tab label="Display Settings" {...a11yProps(4)} />
        </Tabs>
        <Button
          variant="contained"
          size="large"
          sx={{ mb: 1 }}
          onClick={handleSave}
          disabled={saving}
          startIcon={saving ? <CircularProgress size={18} color="inherit" /> : null}
        >
          {saving ? 'Saving…' : 'Save Settings'}
        </Button>
      </Box>

      {/* General Settings */}
      <CustomTabPanel value={tabValue} index={0}>
        <Stack spacing={3} className="marginminds-admin-settings">
          <FormControl>
            <FormControlLabel
              control={<Checkbox checked={settings.enable_gst} onChange={set('enable_gst')} />}
              label="Enable GST System"
            />
            <FormHelperText>Master switch — turns the entire GST plugin on/off</FormHelperText>
          </FormControl>
          <TextField
            fullWidth
            label="Business Legal Name"
            placeholder="ABC Pvt Ltd"
            value={settings.business_legal_name}
            onChange={set('business_legal_name')}
            helperText="Your company's registered name, printed on invoices"
          />
          <TextField
            fullWidth
            label="Store GSTIN"
            placeholder="33ABCDE1234F1Z5"
            value={settings.store_gstin}
            onChange={set('store_gstin')}
            error={!!gstinError}
            helperText={gstinError || '15-character GST Identification Number, shown on invoices and receipts'}
            slotProps={{ htmlInput: { maxLength: 15, style: { textTransform: 'uppercase' } } }}
          />
          <TextField
            fullWidth
            multiline
            rows={4}
            label="Business Address"
            value={settings.business_address}
            onChange={set('business_address')}
            helperText="Your registered business address, printed on invoices"
          />
          <FormControl fullWidth>
            <InputLabel>Business State</InputLabel>
            <Select
              label="Business State"
              value={settings.business_state}
              onChange={set('business_state')}
            >
              {states.map(({ code, name }) => (
                <MenuItem key={code} value={code}>{name}</MenuItem>
              ))}
            </Select>
            <FormHelperText>Your state of registration — used to decide CGST/SGST vs IGST</FormHelperText>
          </FormControl>
        </Stack>
      </CustomTabPanel>

      {/* Tax Configuration */}
      <CustomTabPanel value={tabValue} index={1}>
        <Stack spacing={3}>
          <FormControl>
            <FormControlLabel
              control={<Checkbox checked={settings.enable_cgst_sgst} onChange={set('enable_cgst_sgst')} />}
              label="Enable CGST / SGST"
            />
            <FormHelperText>Applies CGST + SGST split when the customer is in the same state as your business</FormHelperText>
          </FormControl>
          <FormControl>
            <FormControlLabel
              control={<Checkbox checked={settings.enable_igst} onChange={set('enable_igst')} />}
              label="Enable IGST"
            />
            <FormHelperText>Applies IGST when the customer is in a different state (inter-state sales)</FormHelperText>
          </FormControl>
          <FormControl fullWidth>
            <InputLabel>Default GST Rate</InputLabel>
            <Select
              label="Default GST Rate"
              value={settings.default_gst_rate}
              onChange={set('default_gst_rate')}
            >
              {gstRates.map((rate) => (
                <MenuItem key={rate} value={rate}>{rate}</MenuItem>
              ))}
            </Select>
            <FormHelperText>Fallback rate (0%, 5%, 12%, 18%, 28%) used when a product has no specific rate set</FormHelperText>
          </FormControl>
          <FormControl>
            <FormControlLabel
              control={<Checkbox checked={settings.apply_gst_based_on_shipping_state} onChange={set('apply_gst_based_on_shipping_state')} />}
              label="Apply GST Based on Shipping State"
            />
            <FormHelperText>Determines CGST/SGST vs IGST based on the customer's shipping address instead of billing address</FormHelperText>
          </FormControl>
          <FormControl>
            <FormControlLabel
              control={<Checkbox checked={settings.round_tax_amounts} onChange={set('round_tax_amounts')} />}
              label="Round Tax Amounts"
            />
            <FormHelperText>Rounds off the calculated tax to the nearest rupee</FormHelperText>
            <Alert severity="info" sx={{ mt: 1 }}>
              Enable this option only if your business requires rounded GST values.<strong> For precise GST filing and accurate tax reporting, </strong> it is recommended to keep tax rounding disabled.
            </Alert>
          </FormControl>
        </Stack>
      </CustomTabPanel>

      {/* WooCommerce Integration */}
      <CustomTabPanel value={tabValue} index={2}>
        <Stack spacing={3}>
          <FormControl>
            <FormControlLabel
              control={<Checkbox checked={settings.prices_include_gst} onChange={set('prices_include_gst')} />}
              label="Prices Include GST"
            />
            <FormHelperText>If ON, product prices already include GST (tax-inclusive). If OFF, GST is added on top</FormHelperText>
            <Alert severity="info" sx={{ mt: 1 }}>
              Saving this change will automatically update WooCommerce → Tax → <strong>Prices entered with tax</strong> to match.
            </Alert>
          </FormControl>
          <FormControl>
            <FormControlLabel
              control={<Checkbox checked={settings.show_gstin_at_checkout} onChange={set('show_gstin_at_checkout')} />}
              label="Show GSTIN Field at Checkout"
            />
            <FormHelperText>Adds a GSTIN input field on the checkout page for B2B customers</FormHelperText>
          </FormControl>
          <FormControl>
            <FormControlLabel
              control={<Checkbox checked={settings.gstin_field_required} onChange={set('gstin_field_required')} />}
              label="GSTIN Field Required"
            />
            <FormHelperText>Makes the GSTIN field mandatory — customer can't place order without filling it</FormHelperText>
          </FormControl>
          <FormControl>
            <FormControlLabel
              control={<Checkbox checked={settings.save_gstin_to_orders} onChange={set('save_gstin_to_orders')} />}
              label="Save GSTIN to Orders"
            />
            <FormHelperText>Stores the customer's GSTIN in the order record in WooCommerce admin</FormHelperText>
          </FormControl>
          <FormControl>
            <FormControlLabel
              control={<Checkbox checked={settings.enable_gst_on_shipping} onChange={set('enable_gst_on_shipping')} />}
              label="Enable GST on Shipping Charges"
            />
            <FormHelperText>Applies GST on the shipping fee in addition to the product price</FormHelperText>
          </FormControl>
        </Stack>
      </CustomTabPanel>

      {/* Invoice Settings */}
      <CustomTabPanel value={tabValue} index={3}>
        <Stack spacing={3} className="marginminds-admin-settings">
          <FormControl>
            <FormControlLabel
              control={<Checkbox checked={settings.enable_pdf_invoices} onChange={set('enable_pdf_invoices')} />}
              label="Enable PDF Invoices"
            />
            <FormHelperText>Generates a downloadable GST-compliant PDF invoice for each order</FormHelperText>
          </FormControl>
          <Grid container spacing={3}>
            <Grid size={{ xs: 12, md: 6 }}>
              <TextField
                fullWidth
                label="Invoice Prefix"
                placeholder="INV-"
                value={settings.invoice_prefix}
                onChange={set('invoice_prefix')}
                helperText="Text prefix before the invoice number (e.g. INV- makes it INV-1001)"
              />
            </Grid>
          </Grid>
          <FormControl>
            <FormControlLabel
              control={<Checkbox checked={settings.show_gstin_on_invoice} onChange={set('show_gstin_on_invoice')} />}
              label="Show GSTIN on Invoice"
            />
            <FormHelperText>Prints your Store GSTIN on the PDF invoice</FormHelperText>
          </FormControl>
          <FormControl>
            <FormControlLabel
              control={<Checkbox checked={settings.attach_invoice_to_emails} onChange={set('attach_invoice_to_emails')} />}
              label="Attach Invoice to Emails"
            />
            <FormHelperText>Automatically attaches the PDF invoice to order emails sent to the customer</FormHelperText>
          </FormControl>
          <TextField
            fullWidth
            multiline
            rows={3}
            label="Invoice Footer Text"
            placeholder="Thank you for your business"
            value={settings.invoice_footer_text}
            onChange={set('invoice_footer_text')}
            helperText="Custom text printed at the bottom of every invoice"
          />
        </Stack>
      </CustomTabPanel>

      {/* Display Settings */}
      <CustomTabPanel value={tabValue} index={4}>
        <Stack spacing={3}>
          <FormControl>
            <FormControlLabel
              control={<Checkbox checked={settings.show_gst_on_cart} onChange={set('show_gst_on_cart')} />}
              label="Show GST on Cart Page"
            />
            <FormHelperText>Displays a GST breakdown line (CGST/SGST or IGST) in the cart totals</FormHelperText>
          </FormControl>
          <FormControl>
            <FormControlLabel
              control={<Checkbox checked={settings.show_gst_on_checkout} onChange={set('show_gst_on_checkout')} />}
              label="Show GST on Checkout Page"
            />
            <FormHelperText>Displays the GST breakdown on the checkout page</FormHelperText>
          </FormControl>
          <FormControl>
            <FormControlLabel
              control={<Checkbox checked={settings.show_gst_in_order_summary} onChange={set('show_gst_in_order_summary')} />}
              label="Show GST in View Order Page"
            />
            <FormHelperText>Displays the GST breakdown on the View Order page after purchase</FormHelperText>
          </FormControl>
          <FormControl>
            <FormControlLabel
              control={<Checkbox checked={settings.display_gst_below_product_price} onChange={set('display_gst_below_product_price')} />}
              label="Show GST Below Product Price"
            />
            <FormHelperText>Displays a GST amount line under each product's price on shop/product pages</FormHelperText>
          </FormControl>
          <FormControl>
            <FormControlLabel
              control={<Checkbox checked={settings.display_gst_in_order_emails} onChange={set('display_gst_in_order_emails')} />}
              label="Show GST in Order Emails"
            />
            <FormHelperText>Displays a GST breakdown in the order confirmation/processing emails sent to customers</FormHelperText>
          </FormControl>
        </Stack>
      </CustomTabPanel>

      <Snackbar
        open={snackbar.open}
        autoHideDuration={4000}
        onClose={closeSnackbar}
        anchorOrigin={{ vertical: 'bottom', horizontal: 'right' }}
      >
        <Alert severity={snackbar.severity} onClose={closeSnackbar} variant="filled">
          {snackbar.message}
        </Alert>
      </Snackbar>
    </Box>
  );
}

export default App;
