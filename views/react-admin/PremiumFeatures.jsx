import React from 'react';
import {
  Box,
  Button,
  Card,
  CardContent,
  Chip,
  Divider,
  Grid,
  Typography,
} from '@mui/material';

const features = [
  {
    title: 'Advanced GST Reports',
    description: 'Monthly, quarterly, and annual GST reports with GSTR-1 and GSTR-3B summaries, ready to file.',
    badge: 'Pro',
  },
  {
    title: 'HSN / SAC Code Support',
    description: 'Assign HSN codes to products and SAC codes to services. Auto-print on invoices as required by GST law.',
    badge: 'Pro',
  },
  {
    title: 'Download Invoices',
    description: 'Download invoice as CSV or Excel sheets for your accountant or for direct upload to the GST portal.',
    badge: 'Pro',
  },
  {
    title: 'Advanced PDF Invoices',
    description: 'Branded, print-ready PDF invoices with your logo, custom colors, and configurable footer text.',
    badge: 'Pro',
  },
  {
    title: 'B2B / B2C GST Handling',
    description: 'Separate tax treatment for registered businesses (B2B) and end consumers (B2C) with reverse-charge support.',
    badge: 'Pro',
  },
  {
    title: 'Priority Support',
    description: 'Get dedicated support from the Marginminds GST team with guaranteed response times and live chat access.',
    badge: 'Pro',
  },
];

const freeVsPro = [
  { feature: 'GST calculation (CGST / SGST / IGST)',   free: true, pro: true },
  { feature: 'GSTIN field at checkout',                free: true, pro: true },
  { feature: 'Basic HTML invoice',                     free: true, pro: true },
  { feature: 'GST breakdown in order totals',          free: true, pro: true },
  { feature: 'Advanced GST Reports (GSTR-1 / GSTR-3B)',        free: false, pro: true },
  { feature: 'HSN / SAC code support',                 free: false, pro: true },
  { feature: 'Invoice CSV / Excel export',                     free: false, pro: true },
  { feature: 'Branded PDF invoices',                   free: false, pro: true },
  { feature: 'B2B / B2C handling + reverse charge',    free: false, pro: true },
  { feature: 'Priority support',                       free: false, pro: true },
];

function Tick({ ok }) {
  return (
    <Typography
      component="span"
      sx={{ fontWeight: 'bold', color: ok ? 'success.main' : 'text.disabled' }}
    >
      {ok ? '✓' : '✗'}
    </Typography>
  );
}

function PremiumFeatures({ upgradeUrl }) {
  const url = upgradeUrl || 'https://marginminds.com/';

  return (
    <Box sx={{ p: 2, maxWidth: 900 }}>

      {/* Hero */}
      <Box sx={{ mb: 4, textAlign: 'center' }}>
        <Chip label="PRO" color="warning" size="small" sx={{ mb: 1, fontWeight: 'bold', letterSpacing: 1 }} />
        <Typography variant="h4" fontWeight="bold" gutterBottom>
          Upgrade to Marginminds GST Pro
        </Typography>
        <Typography variant="subtitle1" color="text.secondary" sx={{ mb: 3 }}>
          Everything you need for GST compliance in India — reports, HSN codes, and branded invoices.
        </Typography>
        <Button
          variant="contained"
          color="warning"
          size="large"
          href={url}
          target="_blank"
          rel="noopener noreferrer"
          sx={{ fontWeight: 'bold', px: 5 }}
        >
          Upgrade Now
        </Button>
      </Box>

      <Divider sx={{ mb: 4 }} />

      {/* Feature cards */}
      <Typography variant="h6" fontWeight="bold" gutterBottom>
        What&apos;s included in Pro
      </Typography>
      <Grid container spacing={2} sx={{ mb: 5 }}>
        {features.map((f) => (
          <Grid key={f.title} size={{ xs: 12, sm: 6, md: 4 }}>
            <Card variant="outlined" sx={{ height: '100%' }}>
              <CardContent>
                <Box sx={{ display: 'flex', alignItems: 'center', gap: 1, mb: 1 }}>
                  <Typography variant="subtitle1" fontWeight="bold">
                    {f.title}
                  </Typography>
                  <Chip label={f.badge} color="warning" size="small" sx={{ fontWeight: 'bold', fontSize: 10 }} />
                </Box>
                <Typography variant="body2" color="text.secondary">
                  {f.description}
                </Typography>
              </CardContent>
            </Card>
          </Grid>
        ))}
      </Grid>

      {/* Free vs Pro comparison */}
      <Typography variant="h6" fontWeight="bold" gutterBottom>
        Free vs Pro
      </Typography>
      <Card variant="outlined" sx={{ mb: 4 }}>
        <Box sx={{ overflowX: 'auto' }}>
          <Box component="table" sx={{ width: '100%', borderCollapse: 'collapse' }}>
            <Box component="thead">
              <Box component="tr" sx={{ bgcolor: 'grey.50' }}>
                <Box component="th" sx={{ p: 1.5, textAlign: 'left', fontWeight: 'bold', borderBottom: '1px solid', borderColor: 'divider', width: '60%' }}>
                  Feature
                </Box>
                <Box component="th" sx={{ p: 1.5, textAlign: 'center', fontWeight: 'bold', borderBottom: '1px solid', borderColor: 'divider' }}>
                  Free
                </Box>
                <Box component="th" sx={{ p: 1.5, textAlign: 'center', fontWeight: 'bold', borderBottom: '1px solid', borderColor: 'divider', color: 'warning.dark' }}>
                  Pro
                </Box>
              </Box>
            </Box>
            <Box component="tbody">
              {freeVsPro.map((row, i) => (
                <Box
                  component="tr"
                  key={row.feature}
                  sx={{ bgcolor: i % 2 === 0 ? 'transparent' : 'grey.50' }}
                >
                  <Box component="td" sx={{ p: 1.5, borderBottom: '1px solid', borderColor: 'divider' }}>
                    <Typography variant="body2">{row.feature}</Typography>
                  </Box>
                  <Box component="td" sx={{ p: 1.5, textAlign: 'center', borderBottom: '1px solid', borderColor: 'divider' }}>
                    <Tick ok={row.free} />
                  </Box>
                  <Box component="td" sx={{ p: 1.5, textAlign: 'center', borderBottom: '1px solid', borderColor: 'divider' }}>
                    <Tick ok={row.pro} />
                  </Box>
                </Box>
              ))}
            </Box>
          </Box>
        </Box>
      </Card>

      {/* CTA footer */}
      <Box sx={{ textAlign: 'center' }}>
        <Button
          variant="contained"
          color="warning"
          size="large"
          href={url}
          target="_blank"
          rel="noopener noreferrer"
          sx={{ fontWeight: 'bold', px: 5 }}
        >
          Get Marginminds - Gst Pro
        </Button>
      </Box>

    </Box>
  );
}

export default PremiumFeatures;
