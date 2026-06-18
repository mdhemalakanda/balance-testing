import './App.css';
import DisplayUsers from './components/DisplayUsers';
import { Routes, Route, Link } from "react-router-dom";
import UserDetails from './components/UserDetails';

function App() {
  return (
    <>
      <Routes>
        <Route path='/table' element={<DisplayUsers />} />
        <Route path='/user/:userId' element={<UserDetails />} />
      </Routes>
    </>
  );
}

export default App;
