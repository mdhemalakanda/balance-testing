import Paper from '@mui/material/Paper';
import Table from '@mui/material/Table';
import TableBody from '@mui/material/TableBody';
import TableCell from '@mui/material/TableCell';
import TableContainer from '@mui/material/TableContainer';
import TableHead from '@mui/material/TableHead';
import TableRow from '@mui/material/TableRow';
import Typography from '@mui/material/Typography';
import { useEffect, useState } from 'react';
import {useParams} from 'react-router-dom';
import { btFetch } from '../api';
import RoundRulesPanel from './RoundRulesPanel';
import ExerciseAssignmentsPanel from './ExerciseAssignmentsPanel';

const UserDetails = () => {
    const [questions, setQuestions] = useState(null);
    const [progress, setProgress] = useState(null);
    const { userId } = useParams(); 
    const [userIdState, setUserIdState] = useState();
    const [userRatings, setUserRatings] = useState([]);

    useEffect(() => {
        setUserIdState(userId);
    }, [userId])

     // get all ratings.
    useEffect(() => {
        const fetchRatings = async () => {
            try {
                const user_ratings = await btFetch(
                    `ratings?user=${userIdState}`
                );
                const rating_data = await user_ratings.json();
                setUserRatings(rating_data);
            } catch (e) {
                console.error(e);
            }
        };

        if (userIdState) {
            fetchRatings();
        }
    }, [userIdState]);

    // Fetch questions
    useEffect(() => {
        if (!userIdState) return;

        const fetchQuestions = async () => {
            try {
                const response = await btFetch(`users?user=${userIdState}`);
                const data = await response.json();
                const selectedUserQuestions = data?.[String(userIdState)]?.questions ?? [];
                setQuestions(selectedUserQuestions);
            } catch (error) {
                console.error("Error fetching questions:", error);
            }
        };
        fetchQuestions();

        const fetchProgress = async () => {
            try {
                const response = await btFetch(`user_progress?user=${userIdState}`);
                const data = await response.json();
                setProgress(data);
            } catch (error) {
                console.error("Error fetching progress:", error);
            }
        };
        fetchProgress();
    }, [userIdState]);

    if (!progress || !questions) return <div>Loading...</div>;

  // Define rounds dynamically
  const rounds = ["first_round", "second_round", "third_round"];

  const symptomFields = [
    { key: "oireiden_voimakkuus", label: "I. Oireiden voimakkuus" },
    { key: "vaikutus_toimintakykyyn", label: "II. Vaikutus toimintakykyyn" },
  ];


    return (
        <div>
            <Typography variant='h4'>User ID: {userIdState}</Typography>
            <RoundRulesPanel userId={userIdState} />
            <ExerciseAssignmentsPanel userId={userIdState} />
           <div
            style={{
                marginTop: "30px",
                marginRight: "30px",
                padding: "20px",
                background: "#ddd",
                borderRadius: "3px",
            }}
            >
            <Typography variant="h4" sx={{ marginBottom: "10px" }}>
                Progress Summary
            </Typography>
            <TableContainer component={Paper}>
                <Table>
                <TableHead>
                    <TableRow>
                    <TableCell  sx={{
                        backgroundColor: "#1976d2", // blue header
                        color: "#fff",
                        fontWeight: "bold",
                        borderRight: '1px solid #fff'
                    }} colSpan={2}>
                        Huimaus- ja epätasapaino-oireet (asteikko 0–10): 0 = ei oireita / ei haittaa,
                        10 = voimakkain mahdollinen oire / suurin mahdollinen haitta.
                    </TableCell>
                    <TableCell sx={{
                        backgroundColor: "#1976d2", // blue header
                        color: "#fff",
                        borderRight: '1px solid #fff',
                        fontWeight: "bold",
                    }}>1. Harjoituskierros</TableCell>
                    <TableCell sx={{
                        backgroundColor: "#1976d2", // blue header
                        color: "#fff",
                        fontWeight: "bold",
                        borderRight: '1px solid #fff'
                    }}>2. Harjoituskierros</TableCell>
                    <TableCell sx={{
                        backgroundColor: "#1976d2", // blue header
                        color: "#fff",
                        fontWeight: "bold",
                        borderRight: '1px solid #fff'
                    }}>3. Harjoituskierros</TableCell>
                    <TableCell sx={{
                        backgroundColor: "#1976d2", // blue header
                        color: "#fff",
                        fontWeight: "bold",
                        borderRight: '1px solid #fff'
                    }}>Progress Count</TableCell>
                    </TableRow>
                </TableHead>
                <TableBody>
                    {/* Exercise Days */}
                    <TableRow>
                    <TableCell colSpan={2}>Kuinka useana päivänä teit harjoituksia keskimäärin viikon aikana?</TableCell>
                    {rounds.map((r) => (
                        <TableCell key={r}>
                        {progress[r]?.exercise_days ?? ""}
                        </TableCell>
                    ))}
                    <TableCell>-</TableCell>
                    </TableRow>

                    {/* Exercise Frequency */}
                    <TableRow>
                    <TableCell colSpan={2}>Kuinka monta kertaa päivässä teit harjoituksia keskimäärin?</TableCell>
                    {rounds.map((r) => (
                        <TableCell key={r}>
                        {progress[r]?.exercise_frequency ?? ""}
                        </TableCell>
                    ))}
                    <TableCell>-</TableCell>
                    </TableRow>

                    {symptomFields.map((field) => {
                    const values = [
                        progress.initial?.[field.key] ?? "",
                        progress.first_round?.[field.key] ?? "",
                        progress.second_round?.[field.key] ?? "",
                        progress.third_round?.[field.key] ?? "",
                    ];
                    const total = values.reduce((sum, val) => sum + (Number(val) || 0), 0);
                    return (
                        <TableRow key={field.key}>
                        <TableCell>{field.label}</TableCell>
                        {values.map((v, idx) => (
                            <TableCell key={idx}>{v}</TableCell>
                        ))}
                        <TableCell>{total}</TableCell>
                        </TableRow>
                    );
                    })}
                </TableBody>
                </Table>
            </TableContainer>
            </div>

            {
                questions && questions.map((question) => {
                    let round_title = '';
                    switch(parseInt(question.round)) {
                        case 1:
                            round_title = 'Pre Questsions For Round 01';
                            break;
                        case 2:
                            round_title = 'Pre Questions For Round 02';
                            break;
                        case 3:
                            round_title = 'Pre Questions For Round 03';
                            break;
                        case 4:
                            round_title = 'Final Question Answeres';
                            break;
                    }
                    return(
                        (
                        <>
                            <div
                            style={{
                                marginTop: '30px',
                                marginRight: '30px',
                                padding: '20px',
                                background: '#ddd',
                                borderRadius: '3px',
                            }}
                            >
                                <Typography variant='h4' sx={{marginBottom: '10px'}}>{round_title}</Typography>
                                <TableContainer component={Paper}>
                                    <Table sx={{ minWidth: 650 }} aria-label="User Information">
                                        <TableHead>
                                            <TableRow>
                                                <TableCell padding='normal'>
                                                    Field
                                                </TableCell>
                                                <TableCell padding='normal'>
                                                    Value
                                                </TableCell>
                                            </TableRow>
                                        </TableHead>
                                        <TableBody>
                                            <TableRow>
                                                <TableCell component="th" scope="row">
                                                    Etunimi
                                                </TableCell>
                                                    <TableCell component="th" scope="row">
                                                    <Typography variant='p'>{question.user_info.etunimi}</Typography>
                                                </TableCell>
                                            </TableRow>
                                            <TableRow>
                                                <TableCell component="th" scope="row">
                                                    Ikä
                                                </TableCell>
                                                    <TableCell component="th" scope="row">
                                                    <Typography variant='p'>{question.user_info.ika}</Typography>
                                                </TableCell>
                                            </TableRow>
                                            <TableRow>
                                                <TableCell component="th" scope="row">
                                                    Kärsinkö tavallisimmin
                                                </TableCell>
                                                    <TableCell component="th" scope="row">
                                                    <Typography variant='p'>{question.user_info.tavallisimmin}</Typography>
                                                </TableCell>
                                            </TableRow>
                                            {symptomFields.map((field) => (
                                                question.user_info[field.key] !== undefined && question.user_info[field.key] !== '' && (
                                                    <TableRow key={field.key}>
                                                        <TableCell component="th" scope="row">
                                                            {field.label}
                                                        </TableCell>
                                                        <TableCell component="th" scope="row">
                                                            <Typography variant='p'>{question.user_info[field.key]}</Typography>
                                                        </TableCell>
                                                    </TableRow>
                                                )
                                            ))}
                                        
                                            {
                                                question.user_info.dizziness_symptom && (
                                                    <TableRow>
                                                        <TableCell component="th" scope="row">
                                                            Kuinka pitkään olet kärsinyt huimauksesta ja/tai epätasapaino-oireista
                                                        </TableCell>
                                                            <TableCell component="th" scope="row">
                                                            <Typography variant='p'>{question.user_info.user_symptom}</Typography>
                                                        </TableCell>
                                                    </TableRow>
                                                )
                                            }
                                            {
                                                question.user_info.user_symptom && (
                                                    <TableRow>
                                                        <TableCell component="th" scope="row">
                                                            Miten huimaus ja/tai epätasapaino-oireesi yleensä esiintyvät
                                                        </TableCell>
                                                            <TableCell component="th" scope="row">
                                                            <Typography variant='p'>{question.user_info.dizziness_symptom}</Typography>
                                                        </TableCell>
                                                    </TableRow>
                                                )
                                            }
                                            {
                                                question.user_info.user_activity && (
                                                    <TableRow>
                                                        <TableCell component="th" scope="row">
                                                            Oletko huomannut, että lihaskuntosi on heikentynyt tai että väsyisit aiempaa nopeammin fyysisessä rasituksessa
                                                        </TableCell>
                                                            <TableCell component="th" scope="row">
                                                            <Typography variant='p'>{question.user_info.user_activity}</Typography>
                                                        </TableCell>
                                                    </TableRow>
                                                )
                                            }
                                            {
                                                question.user_info.user_second_activity && (
                                                    <TableRow>
                                                        <TableCell component="th" scope="row">
                                                            Kärsitkö huolestuneisuudesta, kuormittuneisuudesta tai pelosta, joka liittyy huimaukseen tai epätasapainon tunteeseen
                                                        </TableCell>
                                                            <TableCell component="th" scope="row">
                                                            <Typography variant='p'>{question.user_info.user_second_activity}</Typography>
                                                        </TableCell>
                                                    </TableRow>
                                                )
                                            }
                                            {
                                                question.user_info.diagnosis_info && (
                                                    <TableRow>
                                                        <TableCell component="th" scope="row">
                                                            Oletko saanut huimaukseen liittyviä diagnooseja? Kirjoita diagnoosit sekä arvioidut vuosiluvut alle. Esim. Hyvänlaatuinen asentohuimaus, 2015 ja 2021.
                                                        </TableCell>
                                                            <TableCell component="th" scope="row">
                                                            <Typography variant='p'>{question.user_info.diagnosis_info}</Typography>
                                                        </TableCell>
                                                    </TableRow>
                                                )
                                            }
                                            {
                                                question.user_info.exercise_days && (
                                                    <TableRow>
                                                        <TableCell component="th" scope="row">
                                                            Kuinka monena päivänä teit suositeltuja harjoituksia? 
                                                        </TableCell>
                                                            <TableCell component="th" scope="row">
                                                            <Typography variant='p'>{question.user_info.exercise_days}</Typography>
                                                        </TableCell>
                                                    </TableRow>
                                                )
                                            }
                                            {
                                                question.user_info.exercise_frequency && (
                                                    <TableRow>
                                                        <TableCell component="th" scope="row">
                                                            Niinä päivinä, kun teit harjoituksia, kuinka monta kertaa päivässä keskimäärin teit ne? 
                                                        </TableCell>
                                                            <TableCell component="th" scope="row">
                                                            <Typography variant='p'>{question.user_info.exercise_frequency}</Typography>
                                                        </TableCell>
                                                    </TableRow>
                                                )
                                            }
                                        </TableBody>
                                    </Table>
                                </TableContainer>
                            </div>

                            {
                                userRatings && Object.keys(userRatings).map((round) => {
                                    if(round === question.round) {
                                        const rating = userRatings[round];
                                        return(
                                            <div
                                                style={{
                                                    marginTop: '30px',
                                                    marginRight: '30px',
                                                    padding: '20px',
                                                    background: 'rgb(32 113 177 / 22%)',
                                                    borderRadius: '3px',
                                                }}
                                                >
                                                    <Typography variant='h4' sx={{marginBottom: '10px'}}>Round 0{round} Result</Typography>
                                                    <TableContainer component={Paper}>
                                                        <Table sx={{ minWidth: 650 }} aria-label="User Information">
                                                            <TableHead>
                                                                <TableRow>
                                                                    <TableCell padding='normal'>
                                                                        Test Name
                                                                    </TableCell>
                                                                    <TableCell padding='normal'>
                                                                        Rating
                                                                    </TableCell>
                                                                </TableRow>
                                                            </TableHead>
                                                            <TableBody>
                                                                {
                                                                rating && rating.map((item, index) => (
                                                                    <TableRow key={index}>
                                                                        <TableCell component="th" scope="row">
                                                                        {item.test_title}
                                                                        </TableCell>
                                                                        <TableCell component="th" scope="row">
                                                                        <Typography>{item.rating}</Typography>
                                                                        </TableCell>
                                                                    </TableRow>
                                                                ))
                                                                }
                                                            </TableBody>
                                                        </Table>
                                                    </TableContainer>
                                                </div>
                                        )
                                    }
                                })
                            }
                        </>
                        )
                    )

                })
            }
        </div>
    )
}
export default UserDetails;