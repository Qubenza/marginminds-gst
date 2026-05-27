import React from 'react';
import {
  Alert,
  Box,
  Button,
  Card,
  CardContent,
  Chip,
  Divider,
  Grid,
  List,
  ListItem,
  ListItemText,
  Typography,
} from '@mui/material';

function Dashboard({ version, settings }) {
  const s = settings || {};

  const checks = [
    { label: 'GST System Enabled',   ok: !!s.enable_gst },
    { label: 'GST Rate Configured',  ok: !!s.default_gst_rate },
    { label: 'Business State Set',   ok: !!s.business_state },
    { label: 'Store GSTIN Entered',  ok: !!s.store_gstin },
    { label: 'Business Name Entered', ok: !!s.business_legal_name },
  ];

  return (
    <Box sx={{ p: 2 }}>

      {/* Status banner */}
      <Alert severity={s.enable_gst ? 'success' : 'warning'} sx={{ mb: 3 }}>
        {s.enable_gst
          ? 'GST is active and processing taxes on your store.'
          : 'GST is currently disabled. Enable it in Settings to start collecting taxes.'}
      </Alert>

      {/* Stat cards */}
      <Grid container spacing={2} sx={{ mb: 3 }}>
        <Grid size={{ xs: 12, sm: 4 }}>
          <Card variant="outlined">
            <CardContent>
              <Typography variant="overline" color="text.secondary" display="block">
                GST Rate
              </Typography>
              <Typography variant="h5">
                {s.default_gst_rate || '—'}
              </Typography>
            </CardContent>
          </Card>
        </Grid>

        <Grid size={{ xs: 12, sm: 4 }}>
          <Card variant="outlined">
            <CardContent>
              <Typography variant="overline" color="text.secondary" display="block">
                Business State
              </Typography>
              <Typography variant="h6" noWrap title={s.business_state}>
                {s.business_state || '—'}
              </Typography>
            </CardContent>
          </Card>
        </Grid>

        <Grid size={{ xs: 12, sm: 4 }}>
          <Card variant="outlined">
            <CardContent>
              <Typography variant="overline" color="text.secondary" display="block">
                Price Mode
              </Typography>
              <Typography variant="h6">
                {s.prices_include_gst ? 'Inclusive' : 'Exclusive'}
              </Typography>
            </CardContent>
          </Card>
        </Grid>
      </Grid>

      {/* Setup checklist */}
      <Typography variant="h6" gutterBottom>Setup Checklist</Typography>
      <Card variant="outlined" sx={{ mb: 3 }}>
        <List dense disablePadding>
          {checks.map((check, i) => (
            <React.Fragment key={check.label}>
              {i > 0 && <Divider />}
              <ListItem>
                <Chip
                  label={check.ok ? '✓' : '✗'}
                  color={check.ok ? 'success' : 'default'}
                  size="small"
                  sx={{ mr: 2, minWidth: 32, fontWeight: 'bold' }}
                />
                <ListItemText primary={check.label} />
              </ListItem>
            </React.Fragment>
          ))}
        </List>
      </Card>

      {/* Quick links */}
      <Typography variant="h6" gutterBottom>Quick Links</Typography>
      <Box sx={{ display: 'flex', gap: 2, flexWrap: 'wrap' }}>
        <Button variant="contained" href="admin.php?page=marginminds-gst-settings">
          Go to Settings
        </Button>
        <Button variant="outlined" href="edit.php?post_type=shop_order">
          View Orders
        </Button>
      </Box>

      <Typography variant="caption" color="text.secondary" sx={{ display: 'block', mt: 3 }}>
        GstMarginminds v{version}
      </Typography>

    </Box>
  );
}

export default Dashboard;
