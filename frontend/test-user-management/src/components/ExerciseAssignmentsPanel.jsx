import Alert from '@mui/material/Alert';
import Autocomplete from '@mui/material/Autocomplete';
import Box from '@mui/material/Box';
import Button from '@mui/material/Button';
import Chip from '@mui/material/Chip';
import CircularProgress from '@mui/material/CircularProgress';
import Divider from '@mui/material/Divider';
import IconButton from '@mui/material/IconButton';
import Link from '@mui/material/Link';
import Paper from '@mui/material/Paper';
import Stack from '@mui/material/Stack';
import Tab from '@mui/material/Tab';
import Tabs from '@mui/material/Tabs';
import Table from '@mui/material/Table';
import TableBody from '@mui/material/TableBody';
import TableCell from '@mui/material/TableCell';
import TableContainer from '@mui/material/TableContainer';
import TableHead from '@mui/material/TableHead';
import TableRow from '@mui/material/TableRow';
import TextField from '@mui/material/TextField';
import Tooltip from '@mui/material/Tooltip';
import Typography from '@mui/material/Typography';
import AddIcon from '@mui/icons-material/Add';
import DragIndicatorIcon from '@mui/icons-material/DragIndicator';
import KeyboardArrowDownIcon from '@mui/icons-material/KeyboardArrowDown';
import KeyboardArrowUpIcon from '@mui/icons-material/KeyboardArrowUp';
import SearchIcon from '@mui/icons-material/Search';
import { useCallback, useEffect, useMemo, useRef, useState } from 'react';
import { btFetch } from '../api';

const formatExerciseLabel = (exercise) => {
  if (!exercise) return '';
  const id = exercise.identifier ? ` (${exercise.identifier})` : '';
  return `${exercise.title}${id}`;
};

const ROUND_TABS = [1, 2, 3];

const sortAssignments = (rows) =>
  [...rows].sort((a, b) => a.sort_order - b.sort_order || a.assignment_id - b.assignment_id);

const statusChip = (status) => {
  switch (status) {
    case 'suggested':
      return <Chip size="small" label="Suggested" color="default" variant="outlined" />;
    case 'approved':
      return <Chip size="small" label="Approved" color="info" />;
    case 'visible':
      return <Chip size="small" label="Visible to user" color="success" />;
    default:
      return <Chip size="small" label={status} />;
  }
};

export default function ExerciseAssignmentsPanel({ userId }) {
  const [data, setData] = useState(null);
  const [loading, setLoading] = useState(false);
  const [saving, setSaving] = useState(false);
  const [error, setError] = useState(null);
  const [notice, setNotice] = useState(null);
  const [roundTab, setRoundTab] = useState(1);
  const [exerciseOptions, setExerciseOptions] = useState([]);
  const [selectedExercise, setSelectedExercise] = useState(null);
  const [exerciseSearchInput, setExerciseSearchInput] = useState('');
  const [exerciseSearchLoading, setExerciseSearchLoading] = useState(false);
  const [movedId, setMovedId] = useState(null);
  const [draggedAssignmentId, setDraggedAssignmentId] = useState(null);
  const [dragOverAssignmentId, setDragOverAssignmentId] = useState(null);
  const exerciseSearchTimer = useRef(null);

  const loadData = useCallback(async () => {
    if (!userId) return;
    setLoading(true);
    setError(null);
    try {
      const response = await btFetch(`user_exercises?user=${userId}`);
      const json = await response.json();
      if (!response.ok) {
        throw new Error(json?.message || 'Failed to load exercises');
      }
      setData(json);
    } catch (e) {
      setError(e.message);
    } finally {
      setLoading(false);
    }
  }, [userId]);

  useEffect(() => {
    loadData();
  }, [loadData]);

  const assignments = data?.assignments ?? [];
  const visibility = data?.visibility ?? { visible: false, days_remaining: null, countdown_message: '' };
  const approvedCount = data?.approved_count ?? 0;
  const maxPerRound = data?.max_per_round ?? 5;

  const sortedForDisplayOrder = useMemo(() => sortAssignments(assignments), [assignments]);

  const positionMap = useMemo(() => {
    const map = {};
    sortedForDisplayOrder.forEach((row, index) => {
      map[row.assignment_id] = index + 1;
    });
    return map;
  }, [sortedForDisplayOrder]);

  const roundAssignments = useMemo(
    () => sortAssignments(assignments.filter((row) => row.round === roundTab)),
    [assignments, roundTab]
  );

  const roundAssignmentCount = roundAssignments.length;

  const roundCounts = useMemo(() => {
    const counts = { 1: 0, 2: 0, 3: 0 };
    assignments.forEach((row) => {
      if (counts[row.round] !== undefined) {
        counts[row.round] += 1;
      }
    });
    return counts;
  }, [assignments]);

  const runAction = async (path, body = {}, successMessage = 'Saved.') => {
    setSaving(true);
    setNotice(null);
    setError(null);
    try {
      const response = await btFetch(`${path}?user=${userId}`, {
        method: 'POST',
        body,
      });
      const json = await response.json();
      if (!response.ok) {
        throw new Error(json?.message || 'Request failed');
      }
      setData(json);
      setNotice(successMessage);
    } catch (e) {
      setError(e.message);
    } finally {
      setSaving(false);
    }
  };

  const handleApproveAll = () =>
    runAction(
      'user_exercises/approve',
      { approve_all: true },
      'All suggested exercises approved. Next: click Display exercises so the user can see them in Harjoitukset.'
    );

  const handleApproveOne = (assignmentId) =>
    runAction(
      'user_exercises/approve',
      { assignment_ids: [assignmentId] },
      'Exercise approved. When all are ready, click Display exercises for the user.'
    );

  const handleDelete = async (assignmentId) => {
    setSaving(true);
    setError(null);
    setNotice(null);
    try {
      const response = await btFetch(`user_exercises/${assignmentId}`, { method: 'DELETE' });
      const json = await response.json();
      if (!response.ok) {
        throw new Error(json?.message || 'Delete failed');
      }
      setData(json);
      setNotice('Exercise removed.');
    } catch (e) {
      setError(e.message);
    } finally {
      setSaving(false);
    }
  };

  const persistDisplayOrder = async (sorted, movedAssignmentId, successMessage) => {
    const items = sorted.map((row, i) => ({
      assignment_id: row.assignment_id,
      sort_order: (i + 1) * 10,
    }));

    const previous = data;
    const optimisticAssignments = assignments.map((row) => {
      const item = items.find((entry) => entry.assignment_id === row.assignment_id);
      return item ? { ...row, sort_order: item.sort_order } : row;
    });

    setData((prev) => ({ ...prev, assignments: optimisticAssignments }));
    setMovedId(movedAssignmentId);
    setNotice(successMessage);
    setError(null);

    const clearHighlight = window.setTimeout(() => setMovedId(null), 2000);

    setSaving(true);
    try {
      const response = await btFetch(`user_exercises/reorder?user=${userId}`, {
        method: 'POST',
        body: { items },
      });
      const json = await response.json();
      if (!response.ok) {
        throw new Error(json?.message || 'Reorder failed');
      }
      setData(json);
    } catch (e) {
      window.clearTimeout(clearHighlight);
      setMovedId(null);
      setData(previous);
      setError(e.message);
      setNotice(null);
    } finally {
      setSaving(false);
    }
  };

  const handleMove = async (assignmentId, direction) => {
    const sorted = [...sortedForDisplayOrder];
    const index = sorted.findIndex((row) => row.assignment_id === assignmentId);
    if (index < 0) return;

    const swapIndex = direction === 'up' ? index - 1 : index + 1;
    if (swapIndex < 0 || swapIndex >= sorted.length) return;

    const movingTitle = sorted[index].exercise_title;
    [sorted[index], sorted[swapIndex]] = [sorted[swapIndex], sorted[index]];

    await persistDisplayOrder(
      sorted,
      assignmentId,
      `Moved "${movingTitle}" to position #${swapIndex + 1}.`
    );
  };

  const handleDragStart = (event, assignmentId) => {
    if (saving) {
      event.preventDefault();
      return;
    }
    setDraggedAssignmentId(assignmentId);
    event.dataTransfer.effectAllowed = 'move';
    event.dataTransfer.setData('text/plain', String(assignmentId));
  };

  const handleDragOver = (event, assignmentId) => {
    if (draggedAssignmentId === null || draggedAssignmentId === assignmentId) return;
    event.preventDefault();
    event.dataTransfer.dropEffect = 'move';
    setDragOverAssignmentId(assignmentId);
  };

  const handleDrop = async (event, dropAssignmentId) => {
    event.preventDefault();
    const sourceId = Number(event.dataTransfer.getData('text/plain') || draggedAssignmentId);

    setDragOverAssignmentId(null);
    setDraggedAssignmentId(null);

    if (!sourceId || sourceId === dropAssignmentId) {
      return;
    }

    const sorted = [...sortedForDisplayOrder];
    const sourceIndex = sorted.findIndex((row) => row.assignment_id === sourceId);
    const targetIndex = sorted.findIndex((row) => row.assignment_id === dropAssignmentId);

    if (sourceIndex < 0 || targetIndex < 0) {
      return;
    }

    const [movedRow] = sorted.splice(sourceIndex, 1);
    sorted.splice(targetIndex, 0, movedRow);

    await persistDisplayOrder(
      sorted,
      sourceId,
      `Moved "${movedRow.exercise_title}" to position #${targetIndex + 1}.`
    );
  };

  const handleDragEnd = () => {
    setDragOverAssignmentId(null);
    setDraggedAssignmentId(null);
  };

  const handleVisibilityToggle = () => {
    if (!visibility.visible && approvedCount < 1) {
      setError('Approve at least one exercise before displaying to the user.');
      return;
    }
    runAction(
      'user_exercises/visibility',
      { visible: !visibility.visible },
      visibility.visible ? 'Exercises hidden from user.' : 'Exercises are now visible to the user.'
    );
  };

  const handleSuggest = () =>
    runAction(
      'user_exercises/suggest',
      { round: roundTab, force: true },
      `Re-suggest completed for round ${roundTab}.`
    );

  const fetchExerciseOptions = useCallback(async (query) => {
    const trimmed = query.trim();
    if (!trimmed) {
      setExerciseOptions([]);
      return;
    }

    setExerciseSearchLoading(true);
    try {
      const response = await btFetch(`user_exercises/search?q=${encodeURIComponent(trimmed)}`);
      const json = await response.json();
      if (!response.ok) {
        throw new Error(json?.message || 'Exercise search failed');
      }
      setExerciseOptions(json?.exercises ?? []);
    } catch (e) {
      setError(e.message);
      setExerciseOptions([]);
    } finally {
      setExerciseSearchLoading(false);
    }
  }, []);

  useEffect(
    () => () => {
      if (exerciseSearchTimer.current) {
        window.clearTimeout(exerciseSearchTimer.current);
      }
    },
    []
  );

  const handleExerciseSearchInput = (_event, value, reason) => {
    if (reason === 'reset') {
      return;
    }

    setExerciseSearchInput(value);

    if (exerciseSearchTimer.current) {
      window.clearTimeout(exerciseSearchTimer.current);
    }

    if (!value.trim()) {
      setExerciseOptions([]);
      setExerciseSearchLoading(false);
      return;
    }

    exerciseSearchTimer.current = window.setTimeout(() => {
      fetchExerciseOptions(value);
    }, 300);
  };

  const handleAddExercise = async () => {
    if (!selectedExercise?.exercise_id) return;
    if (roundAssignmentCount >= maxPerRound) {
      setError(`Maximum ${maxPerRound} exercises per round.`);
      return;
    }
    await runAction(
      'user_exercises/add',
      {
        exercise_id: Number(selectedExercise.exercise_id),
        round: roundTab,
      },
      `Exercise added to round ${roundTab}. It is approved and ready to display.`
    );
    setSelectedExercise(null);
    setExerciseOptions([]);
    setExerciseSearchInput('');
  };

  return (
    <Paper sx={{ p: 2, mt: 3 }} elevation={1}>
      <Typography variant="h5" sx={{ mb: 0.5 }}>
        Exercise assignments
      </Typography>
      <Typography variant="body2" color="text.secondary" sx={{ mb: 2 }}>
        Up to {maxPerRound} exercises per round from tests rated 3 or 4. Approve suggestions, set display order,
        then use &quot;Display exercises&quot; to show them in Harjoitukset.
      </Typography>

      {loading && <Typography sx={{ mb: 2 }}>Loading exercises…</Typography>}

      {visibility.visible ? (
        <Alert severity="success" sx={{ mb: 2 }}>
          <strong>Visible to user.</strong>
          {visibility.countdown_message ? ` ${visibility.countdown_message}` : ''}
        </Alert>
      ) : approvedCount > 0 ? (
        <Alert severity="warning" sx={{ mb: 2 }}>
          <strong>
            {approvedCount} exercise{approvedCount === 1 ? '' : 's'} approved
          </strong>{' '}
          — the user still <strong>cannot see them</strong> in Harjoitukset. Click{' '}
          <strong>Display exercises</strong> below (step 3 after approve).
        </Alert>
      ) : (
        <Alert severity="info" sx={{ mb: 2 }}>
          Exercises are <strong>not visible</strong> to the user yet. Approve assignments, then click{' '}
          <strong>Display exercises</strong>.
        </Alert>
      )}

      {error && (
        <Alert severity="error" sx={{ mb: 2 }} onClose={() => setError(null)}>
          {error}
        </Alert>
      )}
      {notice && (
        <Alert severity="success" sx={{ mb: 2 }} onClose={() => setNotice(null)}>
          {notice}
        </Alert>
      )}

      <Stack direction="row" spacing={1} flexWrap="wrap" useFlexGap sx={{ mb: 2, alignItems: 'center' }}>
        <Button
          variant={visibility.visible ? 'outlined' : 'contained'}
          color={visibility.visible ? 'warning' : 'primary'}
          onClick={handleVisibilityToggle}
          disabled={saving || (!visibility.visible && approvedCount < 1)}
        >
          {visibility.visible ? 'Exercises invisible' : 'Display exercises'}
        </Button>
        <Button variant="outlined" onClick={handleSuggest} disabled={saving}>
          Re-suggest for round
        </Button>
        <Button variant="outlined" onClick={handleApproveAll} disabled={saving}>
          Approve all suggested
        </Button>
        <Chip
          label={`Ready to display: ${approvedCount}`}
          color={approvedCount > 0 ? 'primary' : 'default'}
          variant="outlined"
        />
      </Stack>

      <Divider sx={{ my: 2 }} />

      <Typography variant="h6" sx={{ mb: 0.5 }}>
        User display order
      </Typography>
      <Typography variant="body2" color="text.secondary" sx={{ mb: 1.5 }}>
        This is the exact order shown in <strong>Harjoitukset</strong>. Drag rows using the handle or use arrows —
        the <strong>#</strong> column updates immediately.
      </Typography>

      <TableContainer sx={{ mb: 3, border: '1px solid', borderColor: 'divider', borderRadius: 1 }}>
        <Table size="small">
          <TableHead>
            <TableRow sx={{ bgcolor: 'action.hover' }}>
              <TableCell width={56} align="center">
                #
              </TableCell>
              <TableCell width={62} align="center">
                Drag
              </TableCell>
              <TableCell width={72} align="center">
                Move
              </TableCell>
              <TableCell width={72}>Round</TableCell>
              <TableCell width={100}>ID</TableCell>
              <TableCell>Title</TableCell>
              <TableCell width={140}>Status</TableCell>
            </TableRow>
          </TableHead>
          <TableBody>
            {sortedForDisplayOrder.length === 0 ? (
              <TableRow>
                <TableCell colSpan={7} align="center" sx={{ py: 3, color: 'text.secondary' }}>
                  No assignments yet. Use Re-suggest for round or add an exercise below.
                </TableCell>
              </TableRow>
            ) : (
              sortedForDisplayOrder.map((row, index) => {
                const isFirst = index === 0;
                const isLast = index === sortedForDisplayOrder.length - 1;
                const isMoved = movedId === row.assignment_id;

                return (
                  <TableRow
                    key={`order-${row.assignment_id}`}
                    onDragOver={(event) => handleDragOver(event, row.assignment_id)}
                    onDrop={(event) => handleDrop(event, row.assignment_id)}
                    onDragEnd={handleDragEnd}
                    sx={{
                      bgcolor:
                        dragOverAssignmentId === row.assignment_id
                          ? 'action.selected'
                          : isMoved
                            ? 'success.light'
                            : 'inherit',
                      transition: 'background-color 0.3s ease',
                      opacity: draggedAssignmentId === row.assignment_id ? 0.55 : 1,
                      '&:hover': {
                        bgcolor:
                          dragOverAssignmentId === row.assignment_id
                            ? 'action.selected'
                            : isMoved
                              ? 'success.light'
                              : 'action.hover',
                      },
                    }}
                  >
                    <TableCell align="center">
                      <Typography variant="subtitle1" fontWeight={700} color="primary.main">
                        {index + 1}
                      </Typography>
                    </TableCell>
                    <TableCell align="center">
                      <Tooltip title={saving ? 'Saving order...' : 'Drag to reorder'}>
                        <span>
                          <IconButton
                            size="small"
                            aria-label="Drag row"
                            className="bt-order-drag-handle"
                            draggable={!saving}
                            onDragStart={(event) => handleDragStart(event, row.assignment_id)}
                            onDragEnd={handleDragEnd}
                            disabled={saving}
                          >
                            <DragIndicatorIcon fontSize="small" />
                          </IconButton>
                        </span>
                      </Tooltip>
                    </TableCell>
                    <TableCell align="center">
                      <Stack direction="row" spacing={0} justifyContent="center">
                        <Tooltip title={isFirst ? 'Already first' : 'Move up'}>
                          <span>
                            <IconButton
                              size="small"
                              aria-label="Move up"
                              onClick={() => handleMove(row.assignment_id, 'up')}
                              disabled={saving || isFirst}
                            >
                              <KeyboardArrowUpIcon fontSize="small" />
                            </IconButton>
                          </span>
                        </Tooltip>
                        <Tooltip title={isLast ? 'Already last' : 'Move down'}>
                          <span>
                            <IconButton
                              size="small"
                              aria-label="Move down"
                              onClick={() => handleMove(row.assignment_id, 'down')}
                              disabled={saving || isLast}
                            >
                              <KeyboardArrowDownIcon fontSize="small" />
                            </IconButton>
                          </span>
                        </Tooltip>
                      </Stack>
                    </TableCell>
                    <TableCell>R{row.round}</TableCell>
                    <TableCell>
                      <Typography variant="caption" color="text.secondary">
                        {row.identifier || '—'}
                      </Typography>
                    </TableCell>
                    <TableCell>
                      <Typography variant="body2">{row.exercise_title}</Typography>
                    </TableCell>
                    <TableCell>{statusChip(row.status)}</TableCell>
                  </TableRow>
                );
              })
            )}
          </TableBody>
        </Table>
      </TableContainer>

      <Typography variant="h6" sx={{ mb: 1 }}>
        Manage by round
      </Typography>

      <Tabs value={roundTab - 1} onChange={(_, v) => setRoundTab(v + 1)} sx={{ mb: 2 }}>
        {ROUND_TABS.map((round) => (
          <Tab key={round} label={`Round ${round} (${roundCounts[round]}/${maxPerRound})`} />
        ))}
      </Tabs>

      <TableContainer sx={{ border: '1px solid', borderColor: 'divider', borderRadius: 1 }}>
        <Table size="small">
          <TableHead>
            <TableRow sx={{ bgcolor: 'action.hover' }}>
              <TableCell width={56} align="center">
                #
              </TableCell>
              <TableCell>Identifier</TableCell>
              <TableCell>Title</TableCell>
              <TableCell>Source test</TableCell>
              <TableCell width={72}>Rating</TableCell>
              <TableCell width={140}>Status</TableCell>
              <TableCell align="right" width={180}>
                Actions
              </TableCell>
            </TableRow>
          </TableHead>
          <TableBody>
            {roundAssignments.length === 0 ? (
              <TableRow>
                <TableCell colSpan={7} align="center" sx={{ py: 3, color: 'text.secondary' }}>
                  No assignments for round {roundTab}. ({roundAssignmentCount}/{maxPerRound})
                </TableCell>
              </TableRow>
            ) : (
              roundAssignments.map((row) => (
                <TableRow key={row.assignment_id} hover>
                  <TableCell align="center">
                    <Tooltip title="Position in Harjoitukset">
                      <Typography variant="body2" fontWeight={600} color="primary.main">
                        {positionMap[row.assignment_id] ?? '—'}
                      </Typography>
                    </Tooltip>
                  </TableCell>
                  <TableCell>{row.identifier || '—'}</TableCell>
                  <TableCell>
                    <Typography variant="body2">{row.exercise_title}</Typography>
                    {row.edit_url ? (
                      <Link href={row.edit_url} target="_blank" rel="noopener noreferrer" variant="caption">
                        Edit exercise
                      </Link>
                    ) : null}
                  </TableCell>
                  <TableCell>{row.source_test_title || (row.is_manual ? 'Manual' : '—')}</TableCell>
                  <TableCell>{row.rating ?? '—'}</TableCell>
                  <TableCell>{statusChip(row.status)}</TableCell>
                  <TableCell align="right">
                    <Stack direction="row" spacing={0.5} justifyContent="flex-end">
                      {row.status === 'suggested' && (
                        <Button size="small" variant="outlined" onClick={() => handleApproveOne(row.assignment_id)} disabled={saving}>
                          Approve
                        </Button>
                      )}
                      <Button size="small" color="error" variant="text" onClick={() => handleDelete(row.assignment_id)} disabled={saving}>
                        Remove
                      </Button>
                    </Stack>
                  </TableCell>
                </TableRow>
              ))
            )}
          </TableBody>
        </Table>
      </TableContainer>

      <Box className="bt-exercise-manual-add">
        <Box className="bt-exercise-manual-add__header">
          <Box className="bt-exercise-manual-add__icon" aria-hidden="true">
            <AddIcon fontSize="small" />
          </Box>
          <Box className="bt-exercise-manual-add__intro">
            <Typography variant="subtitle1" component="h3" className="bt-exercise-manual-add__title">
              Add exercise manually
            </Typography>
            <Typography variant="body2" color="text.secondary">
              Search published exercises, select one, then assign it to the active round.
            </Typography>
          </Box>
          <Stack direction="row" spacing={1} className="bt-exercise-manual-add__meta">
            <Chip label={`Round ${roundTab}`} size="small" color="primary" variant="outlined" />
            <Chip
              label={`${roundAssignmentCount} / ${maxPerRound}`}
              size="small"
              variant="outlined"
              color={roundAssignmentCount >= maxPerRound ? 'warning' : 'default'}
            />
          </Stack>
        </Box>

        <Box className="bt-exercise-manual-add__body">
          <Box className="bt-exercise-manual-add__controls">
            <Autocomplete
              fullWidth
              size="medium"
              value={selectedExercise}
              inputValue={exerciseSearchInput}
              options={exerciseOptions}
              loading={exerciseSearchLoading}
              disabled={saving || roundAssignmentCount >= maxPerRound}
              filterOptions={(options) => options}
              getOptionLabel={formatExerciseLabel}
              isOptionEqualToValue={(option, value) => option.exercise_id === value.exercise_id}
              noOptionsText={
                exerciseSearchInput.trim()
                  ? exerciseSearchLoading
                    ? 'Searching…'
                    : 'No exercises matched your search'
                  : 'Type to search exercises'
              }
              onChange={(_event, value) => setSelectedExercise(value)}
              onInputChange={handleExerciseSearchInput}
              renderOption={(props, option) => {
                const { key, ...optionProps } = props;
                return (
                  <Box component="li" key={key} {...optionProps} className="bt-exercise-manual-add__option">
                    <Typography variant="body2" className="bt-exercise-manual-add__option-title">
                      {option.title}
                    </Typography>
                    {option.identifier ? (
                      <Typography variant="caption" color="text.secondary">
                        {option.identifier}
                      </Typography>
                    ) : null}
                  </Box>
                );
              }}
              renderInput={(params) => (
                <TextField
                  {...params}
                  placeholder="Search by exercise name or ID…"
                  hiddenLabel
                  className="bt-exercise-manual-add__search"
                  slotProps={{
                    input: {
                      ...params.InputProps,
                      startAdornment: (
                        <>
                          <SearchIcon fontSize="small" className="bt-exercise-manual-add__search-icon" />
                          {params.InputProps.startAdornment}
                        </>
                      ),
                      endAdornment: (
                        <>
                          {exerciseSearchLoading ? <CircularProgress color="inherit" size={18} /> : null}
                          {params.InputProps.endAdornment}
                        </>
                      ),
                    },
                  }}
                />
              )}
            />

            <Button
              variant="contained"
              color="primary"
              className="bt-exercise-manual-add__submit"
              startIcon={<AddIcon />}
              onClick={handleAddExercise}
              disabled={saving || !selectedExercise || roundAssignmentCount >= maxPerRound}
            >
              Add to round {roundTab}
            </Button>
          </Box>

          {selectedExercise ? (
            <Box className="bt-exercise-manual-add__preview">
              <Typography variant="caption" color="text.secondary" className="bt-exercise-manual-add__preview-label">
                Selected
              </Typography>
              <Chip
                label={formatExerciseLabel(selectedExercise)}
                onDelete={() => {
                  setSelectedExercise(null);
                  setExerciseSearchInput('');
                  setExerciseOptions([]);
                }}
                color="primary"
                variant="outlined"
                className="bt-exercise-manual-add__preview-chip"
              />
            </Box>
          ) : (
            <Typography variant="caption" color="text.secondary" className="bt-exercise-manual-add__hint">
              {roundAssignmentCount >= maxPerRound
                ? `Round ${roundTab} already has the maximum of ${maxPerRound} exercises.`
                : 'Pick an exercise from the search results to enable the add button.'}
            </Typography>
          )}
        </Box>
      </Box>
    </Paper>
  );
}
