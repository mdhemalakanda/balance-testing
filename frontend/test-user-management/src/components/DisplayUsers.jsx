import Table from '@mui/material/Table';
import TableBody from '@mui/material/TableBody';
import TableCell from '@mui/material/TableCell';
import TableContainer from '@mui/material/TableContainer';
import TableHead from '@mui/material/TableHead';
import TableRow from '@mui/material/TableRow';
import Paper from '@mui/material/Paper';
import Button from '@mui/material/Button';
import Typography from '@mui/material/Typography';
import Select from '@mui/material/Select';
import FormControl from '@mui/material/FormControl';
import InputLabel from '@mui/material/InputLabel';
import MenuItem from '@mui/material/MenuItem';
import { useEffect, useState } from 'react';
import { btFetch } from '../api';

const TAB_STORAGE_KEY = 'bt_test_users_current_status';

export default function DisplayUsers() {
  const getInitialStatus = () => {
    const savedStatus = window.localStorage.getItem(TAB_STORAGE_KEY);
    return savedStatus === 'trash' ? 'trash' : 'publish';
  };

  const [users, setUsers] = useState([]);
  const [allUsers, setAllUsers] = useState([]);
  const [currentStatus, setCurrentStatus] = useState(getInitialStatus);
  const [selectedRound, setSelectedRound] = useState('');

  const buildUsersPath = (params = {}, status = currentStatus) => {
    const search = new URLSearchParams();
    search.set('status', status);
    Object.entries(params).forEach(([key, value]) => {
      if (value !== '' && value !== undefined && value !== null) {
        search.set(key, value);
      }
    });
    return `users?${search.toString()}`;
  };

  const fetchUsers = async (params = {}, status = currentStatus) => {
    const response = await btFetch(buildUsersPath(params, status));
    const data = await response.json();
    const dataArr = Object.values(data).filter((user) => {
      return Boolean(user?.user_id) && Array.isArray(user?.questions) && user.questions.length > 0;
    });
    setUsers(dataArr);
    setAllUsers(dataArr);
  };

  useEffect(() => {
    window.localStorage.setItem(TAB_STORAGE_KEY, currentStatus);
  }, [currentStatus]);

  useEffect(() => {
    const params = selectedRound ? { round: selectedRound } : {};
    fetchUsers(params);
  }, [currentStatus, selectedRound]);
  

  const handleFilter = async (e) => {
    const round = e.target.value;
    setSelectedRound(round);
    const params = round ? { round } : {};
    fetchUsers(params);
  }

  const handleUserAction = async (user, deleteType) => {
    const actionLabel = deleteType === 'publish' ? 'restore' : deleteType === 'delete' ? 'delete permanently' : 'move to trash';
    const isConfirmed = window.confirm(`Are you sure you want to ${actionLabel}?`);
    if(isConfirmed) {
      const userId = user?.user_id;
  
      try {
        const response = await btFetch(
          `user_progress?user=${userId}&delete_type=${deleteType}`,
          { method: 'DELETE' }
        );
  
        if (response.ok) {
          if (deleteType === 'publish') {
            // After restore, move to Publish and reload full list
            // so restored users are immediately visible.
            setSelectedRound('');
            setCurrentStatus('publish');
            fetchUsers({}, 'publish');
          } else {
            setUsers((prevUsers) => {
              return prevUsers.filter((u) => u.user_id !== userId);
            });
            setAllUsers((prevUsers) => {
              return prevUsers.filter((u) => u.user_id !== userId);
            });
          }
        }
  
      } catch (error) {
        console.error("User action failed:", error);
      }
    }
  };

  const handleUserSearch = (e) => {
    e.preventDefault();

    const searchInput = e.target.search.value.trim().toLowerCase();

    if (!searchInput) {
      setUsers(allUsers);
      return;
    }

    const filteredUsers = allUsers.filter(user =>
      user.user_name?.toLowerCase().includes(searchInput) ||
      user.questions[0]?.user_info?.name?.toLowerCase().includes(searchInput)
    );

    setUsers(filteredUsers);
  };


  return (
    <>
    <div className="test-user-filter-box">
     <div className="test-user-filter-root-head">
        <div className='test-user-filter-flex'>
            <Typography variant='h6'>Filter:</Typography>
            <div className="filter-box">
              <FormControl fullWidth>
                <InputLabel id="demo-simple-select-label">Round</InputLabel>
                <Select
                  labelId="demo-simple-select-label"
                  id="demo-simple-select"
                  value={selectedRound}
                  onChange={handleFilter}
                  label="Round"
                >
                  <MenuItem value=''>All</MenuItem>
                  <MenuItem value={1}>Round 01</MenuItem>
                  <MenuItem value={2}>Round 02</MenuItem>
                  <MenuItem value={3}>Round 03</MenuItem>
                </Select>
              </FormControl>
            </div>
        </div>
        <form className="test-user-filter-searchbox" onSubmit={handleUserSearch}>
          <input type="search" name="search" id="search" placeholder='Search...' />
          <button type="submit">Search</button>
        </form>
     </div>
    </div>
    <div className="test-user-status-filter">
      <Button variant={currentStatus === 'publish' ? 'contained' : 'outlined'} onClick={() => setCurrentStatus('publish')}>Publish</Button>
      <Button variant={currentStatus === 'trash' ? 'contained' : 'outlined'} style={{marginLeft: '10px'}} onClick={() => setCurrentStatus('trash')}>Trash</Button>
    </div>
     <div className="test-user-view-table">
      <TableContainer component={Paper}>
        <Table sx={{ minWidth: 650 }} aria-label="simple table">
          <TableHead>
            <TableRow>
              <TableCell padding='normal'>
                User Name
              </TableCell>
              <TableCell padding='normal'>
                Round
              </TableCell>
              <TableCell padding='normal'>
                Action
              </TableCell>
            </TableRow>
          </TableHead>
          <TableBody>
            {
              users.map((user) => {
                  const rounds = [
                  ...new Set(user.questions.map(q => q.user_info?.round))
                ].join(", ");
    
                  return (
                    <TableRow
                      key={user.user_id}
                      sx={{ '&:last-child td, &:last-child th': { border: 0 } }}
                    >
                      {/* USER NAME */}
                      <TableCell component="th" scope="row">
                        {user.questions[0]?.user_info?.name ?? `${user.user_name}`}
                      </TableCell>
    
                      {/* ROUNDS (grouped) */}
                      <TableCell>
                        {rounds}
                      </TableCell>
    
                      {/* ACTION */}
                      <TableCell>
                       <Button
                          variant="contained"
                          onClick={() => {
                            const url = new URL(window.location.href); 
    
                            url.searchParams.set('user_id', user.user_id);
    
                            url.hash = `/test/user/${user.user_id}`;
    
                            window.location.href = url.toString();
                          }}
                        >
                          View
                        </Button>
                        {
                          currentStatus === 'trash' && (
                            <Button variant='outlined' style={{marginLeft: '10px'}} onClick={() => handleUserAction(user, 'publish')}>
                              Restore
                            </Button>
                          )
                        }
                        <Button
                          variant='outlined'
                          style={{marginLeft: '10px'}}
                          onClick={() => handleUserAction(user, currentStatus === 'trash' ? 'delete' : 'trash')}
                        >
                          {currentStatus === 'trash' ? 'Delete Permanently' : 'Trash'}
                        </Button>
                      </TableCell>
                    </TableRow>
                  );
                })
            }
          </TableBody>
        </Table>
     </TableContainer>
     </div>
    </>
  );
}
