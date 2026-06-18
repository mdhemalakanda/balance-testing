import Box from '@mui/material/Box';
import Button from '@mui/material/Button';
import Paper from '@mui/material/Paper';
import Table from '@mui/material/Table';
import TableBody from '@mui/material/TableBody';
import TableCell from '@mui/material/TableCell';
import TableContainer from '@mui/material/TableContainer';
import TableHead from '@mui/material/TableHead';
import TableRow from '@mui/material/TableRow';
import TextField from '@mui/material/TextField';
import Typography from '@mui/material/Typography';
import { useCallback, useEffect, useState } from 'react';
import { btFetch } from '../api';

const ROUND_NUMBERS = [1, 2, 3];

const emptyForm = () => ({
  1: { max_tests: '', rating_threshold: '' },
  2: { max_tests: '', rating_threshold: '' },
  3: { max_tests: '', rating_threshold: '' },
});

function formFromPayload(payload) {
  const form = emptyForm();
  if (!payload?.rounds) {
    return form;
  }
  ROUND_NUMBERS.forEach((round) => {
    const r = payload.rounds[round];
    if (!r) return;
    if (r.max_tests?.override != null && r.max_tests.override !== '') {
      form[round].max_tests = String(r.max_tests.override);
    }
    if (r.rating_threshold?.override != null && r.rating_threshold.override !== '') {
      form[round].rating_threshold = String(r.rating_threshold.override);
    }
  });
  return form;
}

export default function RoundRulesPanel({ userId }) {
  const [payload, setPayload] = useState(null);
  const [form, setForm] = useState(emptyForm);
  const [loading, setLoading] = useState(false);
  const [saving, setSaving] = useState(false);
  const [error, setError] = useState(null);
  const [notice, setNotice] = useState(null);

  const loadRules = useCallback(async () => {
    if (!userId) return;
    setLoading(true);
    setError(null);
    try {
      const res = await btFetch(`round_rules?user=${userId}`);
      if (!res.ok) {
        throw new Error('Could not load per-round limits.');
      }
      const data = await res.json();
      setPayload(data);
      setForm(formFromPayload(data));
    } catch (e) {
      setError(e.message || 'Failed to load limits.');
      setPayload(null);
    } finally {
      setLoading(false);
    }
  }, [userId]);

  useEffect(() => {
    loadRules();
  }, [loadRules]);

  const handleField = (round, field, value) => {
    setForm((prev) => ({
      ...prev,
      [round]: { ...prev[round], [field]: value },
    }));
  };

  const handleSave = async () => {
    setSaving(true);
    setNotice(null);
    setError(null);
    try {
      const rounds = {};
      ROUND_NUMBERS.forEach((round) => {
        const row = form[round];
        const entry = {};
        if (row.max_tests !== '') {
          entry.max_tests = parseInt(row.max_tests, 10);
        }
        if (row.rating_threshold !== '') {
          entry.rating_threshold = parseInt(row.rating_threshold, 10);
        }
        if (Object.keys(entry).length) {
          rounds[round] = entry;
        }
      });

      const res = await btFetch(`round_rules?user=${userId}`, {
        method: 'POST',
        body: { rounds },
      });
      if (!res.ok) {
        const err = await res.json().catch(() => ({}));
        throw new Error(err.message || 'Save failed.');
      }
      const data = await res.json();
      setPayload(data);
      setForm(formFromPayload(data));
      setNotice('Limits saved.');
    } catch (e) {
      setError(e.message || 'Save failed.');
    } finally {
      setSaving(false);
    }
  };

  const handleClear = async () => {
    setSaving(true);
    setNotice(null);
    setError(null);
    try {
      const res = await btFetch(`round_rules?user=${userId}`, {
        method: 'POST',
        body: { rounds: {} },
      });
      if (!res.ok) {
        throw new Error('Could not clear overrides.');
      }
      const data = await res.json();
      setPayload(data);
      setForm(emptyForm());
      setNotice('Overrides cleared. Plugin defaults apply.');
    } catch (e) {
      setError(e.message || 'Clear failed.');
    } finally {
      setSaving(false);
    }
  };

  if (!userId) {
    return null;
  }

  const userLabel = payload?.user_name || payload?.user_login || `User #${userId}`;
  const currentRound = payload?.current_round ?? '—';

  return (
    <Paper sx={{ p: 2, mt: 3, mb: 3 }} variant="outlined">
      <Typography variant="h5" component="h2" gutterBottom>
        Per-round test limits
      </Typography>
      <Typography variant="body2" color="text.secondary" sx={{ mb: 2 }}>
        Set how many tests this user may complete in each round, and how many ratings of 3 or 4 are
        required to advance. Leave a field empty to use the plugin default (round 1–2: max tests =
        43, round 3: 42; threshold = 6 for all rounds).
      </Typography>
      <Typography variant="body2" sx={{ mb: 2 }}>
        User: <strong>{userLabel}</strong> — current round: <strong>{currentRound}</strong>
      </Typography>

      {error && (
        <Typography color="error" sx={{ mb: 1 }}>
          {error}
        </Typography>
      )}
      {notice && (
        <Typography color="success.main" sx={{ mb: 1 }}>
          {notice}
        </Typography>
      )}

      <TableContainer>
        <Table size="small">
          <TableHead>
            <TableRow>
              <TableCell>Round</TableCell>
              <TableCell>Max tests (override)</TableCell>
              <TableCell>Default max</TableCell>
              <TableCell>Completed</TableCell>
              <TableCell>3/4 threshold (override)</TableCell>
              <TableCell>Default threshold</TableCell>
              <TableCell>3/4 count</TableCell>
            </TableRow>
          </TableHead>
          <TableBody>
            {ROUND_NUMBERS.map((round) => {
              const r = payload?.rounds?.[round] ?? {};
              const maxDefault = r.max_tests?.default ?? '—';
              const threshDefault = r.rating_threshold?.default ?? 6;
              const completed = r.tests_completed ?? 0;
              const maxEffective = r.max_tests?.effective ?? maxDefault;
              const valid34 = r.valid_ratings_3_4 ?? 0;
              const threshEffective = r.rating_threshold?.effective ?? threshDefault;

              return (
                <TableRow key={round}>
                  <TableCell>Round {round}</TableCell>
                  <TableCell>
                    <TextField
                      size="small"
                      type="number"
                      inputProps={{ min: 1 }}
                      placeholder={String(maxDefault)}
                      value={form[round].max_tests}
                      onChange={(e) => handleField(round, 'max_tests', e.target.value)}
                      disabled={loading || saving}
                      sx={{ width: 90 }}
                    />
                  </TableCell>
                  <TableCell>{maxDefault}</TableCell>
                  <TableCell>
                    {completed} / {maxEffective}
                  </TableCell>
                  <TableCell>
                    <TextField
                      size="small"
                      type="number"
                      inputProps={{ min: 1 }}
                      placeholder={String(threshDefault)}
                      value={form[round].rating_threshold}
                      onChange={(e) => handleField(round, 'rating_threshold', e.target.value)}
                      disabled={loading || saving}
                      sx={{ width: 90 }}
                    />
                  </TableCell>
                  <TableCell>{threshDefault}</TableCell>
                  <TableCell>
                    {valid34} / {threshEffective}
                  </TableCell>
                </TableRow>
              );
            })}
          </TableBody>
        </Table>
      </TableContainer>

      <Box sx={{ mt: 2, display: 'flex', gap: 1, flexWrap: 'wrap', alignItems: 'center' }}>
        <Button variant="contained" onClick={handleSave} disabled={loading || saving}>
          Save limits
        </Button>
        <Button variant="outlined" onClick={handleClear} disabled={loading || saving}>
          Clear overrides (use defaults)
        </Button>
        <Button variant="text" onClick={loadRules} disabled={loading || saving}>
          Reload
        </Button>
      </Box>
    </Paper>
  );
}
